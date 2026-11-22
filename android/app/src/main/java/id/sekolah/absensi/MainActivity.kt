package id.sekolah.absensi

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.google.android.gms.tasks.CancellationTokenSource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Locale

class MainActivity : AppCompatActivity() {

    private lateinit var tvSekolah: TextView
    private lateinit var tvNama: TextView
    private lateinit var tvTanggal: TextView
    private lateinit var tvMasuk: TextView
    private lateinit var tvPulang: TextView
    private lateinit var tvInfo: TextView
    private lateinit var tvRiwayat: TextView
    private lateinit var btnMasuk: Button
    private lateinit var btnPulang: Button
    private lateinit var progress: ProgressBar

    private var busy = false
    private var sudahMasuk = false
    private var sudahPulang = false
    private var pendingTipe: String? = null

    private val fused by lazy { LocationServices.getFusedLocationProviderClient(this) }

    private val permLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            val t = pendingTipe
            pendingTipe = null
            if (granted && t != null) doAbsen(t)
            else Toast.makeText(this, "Izin lokasi presisi diperlukan untuk absen", Toast.LENGTH_LONG).show()
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        tvSekolah = findViewById(R.id.tvSekolah)
        tvNama = findViewById(R.id.tvNama)
        tvTanggal = findViewById(R.id.tvTanggal)
        tvMasuk = findViewById(R.id.tvMasuk)
        tvPulang = findViewById(R.id.tvPulang)
        tvInfo = findViewById(R.id.tvInfo)
        tvRiwayat = findViewById(R.id.tvRiwayat)
        btnMasuk = findViewById(R.id.btnMasuk)
        btnPulang = findViewById(R.id.btnPulang)
        progress = findViewById(R.id.progress)

        btnMasuk.setOnClickListener { mulaiAbsen("masuk") }
        btnPulang.setOnClickListener { mulaiAbsen("pulang") }
        findViewById<Button>(R.id.btnLogout).setOnClickListener { logout(null) }
    }

    override fun onResume() {
        super.onResume()
        loadStatus()
    }

    private fun setBusy(b: Boolean) {
        busy = b
        progress.visibility = if (b) View.VISIBLE else View.GONE
        refreshButtons()
    }

    private fun refreshButtons() {
        btnMasuk.isEnabled = !busy && !sudahMasuk
        btnPulang.isEnabled = !busy && sudahMasuk && !sudahPulang
    }

    private fun logout(msg: String?) {
        Session.clear(this)
        startActivity(Intent(this, LoginActivity::class.java).apply { msg?.let { putExtra("msg", it) } })
        finish()
    }

    /** true jika sesi/perangkat ditolak server dan pengguna sudah dikeluarkan. */
    private fun handleAuth(r: ApiResult): Boolean {
        if (r.code == 401 || r.error == "device_mismatch" || r.error == "device_missing") {
            logout(r.message)
            return true
        }
        return false
    }

    private fun loadStatus() {
        lifecycleScope.launch {
            setBusy(true)
            val r = withContext(Dispatchers.IO) { Api.call(this@MainActivity, "status.php") }
            setBusy(false)
            if (handleAuth(r)) return@launch
            if (!r.ok) {
                tvInfo.text = r.message
                return@launch
            }
            val j = r.json
            tvSekolah.text = j.optString("sekolah")
            tvNama.text = j.optString("nama")
            tvTanggal.text = fmtTanggal(j.optString("tanggal"))

            val masuk = j.optJSONObject("masuk")
            val pulang = j.optJSONObject("pulang")
            sudahMasuk = masuk != null
            sudahPulang = pulang != null
            tvMasuk.text = if (masuk != null) "Masuk: ${masuk.optString("jam")} (${masuk.optString("status")})"
            else "Masuk: belum absen (mulai ${j.optString("jam_masuk")})"
            tvPulang.text = if (pulang != null) "Pulang: ${pulang.optString("jam")}"
            else "Pulang: belum absen (mulai ${j.optString("jam_pulang")})"
            tvInfo.text = "Absen hanya bisa dilakukan dalam radius ${j.optInt("radius")} m dari lokasi."
            refreshButtons()

            val sb = StringBuilder()
            val arr = j.optJSONArray("riwayat")
            if (arr != null) for (i in 0 until arr.length()) {
                val o = arr.getJSONObject(i)
                val m = if (o.isNull("masuk")) "-" else o.getString("masuk")
                val p = if (o.isNull("pulang")) "-" else o.getString("pulang")
                val s = if (o.isNull("status")) "" else "  [${o.getString("status")}]"
                sb.append(o.getString("tanggal")).append("   masuk ").append(m).append("   pulang ").append(p).append(s).append('\n')
            }
            tvRiwayat.text = if (sb.isEmpty()) "Belum ada riwayat" else sb.toString().trimEnd()
        }
    }

    private fun mulaiAbsen(tipe: String) {
        val fine = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION)
        if (fine != PackageManager.PERMISSION_GRANTED) {
            pendingTipe = tipe
            permLauncher.launch(Manifest.permission.ACCESS_FINE_LOCATION)
            return
        }
        doAbsen(tipe)
    }

    @SuppressLint("MissingPermission")
    private fun doAbsen(tipe: String) {
        setBusy(true)
        tvInfo.text = "Mengambil lokasi GPS..."
        val cts = CancellationTokenSource()
        fused.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, cts.token)
            .addOnSuccessListener { loc ->
                if (loc == null) {
                    setBusy(false)
                    tvInfo.text = "Lokasi tidak tersedia. Aktifkan GPS lalu coba lagi."
                } else {
                    kirimAbsen(tipe, loc)
                }
            }
            .addOnFailureListener {
                setBusy(false)
                tvInfo.text = "Gagal mengambil lokasi. Pastikan GPS aktif."
            }
    }

    private fun kirimAbsen(tipe: String, loc: android.location.Location) {
        lifecycleScope.launch {
            val body = JSONObject()
                .put("tipe", tipe)
                .put("lat", loc.latitude)
                .put("lng", loc.longitude)
                .put("akurasi", loc.accuracy.toDouble())
                .put("is_mock", MockDetector.isMock(loc))
            val r = withContext(Dispatchers.IO) { Api.call(this@MainActivity, "absen.php", "POST", body) }
            if (handleAuth(r)) return@launch
            setBusy(false)
            tvInfo.text = r.message
            Toast.makeText(this@MainActivity, r.message, Toast.LENGTH_LONG).show()
            if (r.ok) loadStatus()
        }
    }

    private fun fmtTanggal(iso: String): String = try {
        val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso)!!
        SimpleDateFormat("EEEE, d MMMM yyyy", Locale("id", "ID")).format(d)
    } catch (e: Exception) {
        iso
    }
}
