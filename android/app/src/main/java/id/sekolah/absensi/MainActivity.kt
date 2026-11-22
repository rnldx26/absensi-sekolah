package id.sekolah.absensi

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Location
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
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
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
    private lateinit var btnDaftar: Button
    private lateinit var progress: ProgressBar

    private var busy = false
    private var faceEnrolled = true
    private var sudahMasuk = false
    private var sudahPulang = false
    private var enrollPrompted = false
    private var pendingTipe: String? = null
    private var pendingLoc: Location? = null
    private var faceMode: String? = null

    private val fused by lazy { LocationServices.getFusedLocationProviderClient(this) }

    private val permLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            val t = pendingTipe
            if (granted && t != null) {
                doAbsen(t)
            } else {
                pendingTipe = null
                Toast.makeText(this, "Izin lokasi presisi diperlukan untuk absen", Toast.LENGTH_LONG).show()
            }
        }

    private val faceLauncher =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { res ->
            val mode = faceMode
            faceMode = null
            val data = res.data
            val emb = data?.getFloatArrayExtra(FaceActivity.RESULT_EMB)
            val photo = data?.getStringExtra(FaceActivity.RESULT_PHOTO)
            if (res.resultCode != RESULT_OK || emb == null || photo == null) {
                setBusy(false)
                tvInfo.text = if (mode == "enroll") "> pendaftaran wajah dibatalkan" else "> verifikasi wajah dibatalkan"
                return@registerForActivityResult
            }
            if (mode == "enroll") kirimEnroll(emb, photo) else kirimAbsen(emb, photo)
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
        btnDaftar = findViewById(R.id.btnDaftar)
        progress = findViewById(R.id.progress)

        btnMasuk.setOnClickListener { mulaiAbsen("masuk") }
        btnPulang.setOnClickListener { mulaiAbsen("pulang") }
        btnDaftar.setOnClickListener { launchFace("enroll") }
        findViewById<Button>(R.id.btnLogout).setOnClickListener { logout(null, null) }
    }

    override fun onResume() {
        super.onResume()
        loadStatus()
    }

    // ---------- UI helpers ----------

    private fun setBusy(b: Boolean) {
        busy = b
        progress.visibility = if (b) View.VISIBLE else View.GONE
        refreshButtons()
    }

    private fun refreshButtons() {
        btnMasuk.isEnabled = !busy && faceEnrolled && !sudahMasuk
        btnPulang.isEnabled = !busy && faceEnrolled && sudahMasuk && !sudahPulang
        btnDaftar.visibility = if (faceEnrolled) View.GONE else View.VISIBLE
        btnDaftar.isEnabled = !busy
    }

    private fun logout(title: String?, msg: String?) {
        Session.clear(this)
        startActivity(Intent(this, LoginActivity::class.java).apply {
            title?.let { putExtra("title", it) }
            msg?.let { putExtra("msg", it) }
        })
        finish()
    }

    /** true jika sesi/perangkat ditolak server dan pengguna sudah dikeluarkan. */
    private fun handleAuth(r: ApiResult): Boolean {
        return when {
            r.error == "device_mismatch" -> {
                logout("⚠ PERANGKAT TIDAK TERDAFTAR", deviceMessage(r.json)); true
            }
            r.code == 401 || r.error == "device_missing" -> {
                logout(null, r.message); true
            }
            else -> false
        }
    }

    // ---------- Status ----------

    private fun loadStatus() {
        lifecycleScope.launch {
            setBusy(true)
            val r = withContext(Dispatchers.IO) { Api.call(this@MainActivity, "status.php") }
            setBusy(false)
            if (handleAuth(r)) return@launch
            if (!r.ok) {
                tvInfo.text = "> " + r.message
                return@launch
            }
            val j = r.json
            tvSekolah.text = "root@" + j.optString("sekolah").lowercase(Locale.ROOT).replace(" ", "-") + ":~$ whoami"
            tvNama.text = j.optString("nama")
            tvTanggal.text = fmtTanggal(j.optString("tanggal"))

            val masuk = j.optJSONObject("masuk")
            val pulang = j.optJSONObject("pulang")
            sudahMasuk = masuk != null
            sudahPulang = pulang != null
            faceEnrolled = j.optBoolean("face_enrolled", false)
            tvMasuk.text = if (masuk != null) "MASUK  : ${masuk.optString("jam")} [${masuk.optString("status")}]"
            else "MASUK  : -- belum absen (mulai ${j.optString("jam_masuk")})"
            tvPulang.text = if (pulang != null) "PULANG : ${pulang.optString("jam")}"
            else "PULANG : -- belum absen (mulai ${j.optString("jam_pulang")})"

            val lok = j.optJSONArray("lokasi")
            val daftar = StringBuilder()
            if (lok != null) for (i in 0 until lok.length()) daftar.append(if (i > 0) ", " else "").append(lok.getString(i))
            tvInfo.text = if (!faceEnrolled) "> wajah belum terdaftar, pendaftaran wajib"
            else if (daftar.isEmpty()) "> belum ada lokasi absen aktif, hubungi admin"
            else "> lokasi absen aktif: $daftar"
            refreshButtons()

            val sb = StringBuilder()
            val arr = j.optJSONArray("riwayat")
            if (arr != null) for (i in 0 until arr.length()) {
                val o = arr.getJSONObject(i)
                val m = if (o.isNull("masuk")) "--:--" else o.getString("masuk")
                val p = if (o.isNull("pulang")) "--:--" else o.getString("pulang")
                val s = if (o.isNull("status")) "" else " [${o.getString("status")}]"
                sb.append(o.getString("tanggal")).append("  in ").append(m).append("  out ").append(p).append(s).append('\n')
            }
            tvRiwayat.text = if (sb.isEmpty()) "(kosong)" else sb.toString().trimEnd()

            if (!faceEnrolled && !enrollPrompted) promptEnroll()
        }
    }

    // ---------- Wajah ----------

    private fun promptEnroll() {
        enrollPrompted = true
        MaterialAlertDialogBuilder(this)
            .setTitle("DAFTARKAN WAJAH")
            .setMessage(
                "Wajah Anda belum terdaftar. Pendaftaran wajib dilakukan sebelum bisa absen.\n\n" +
                    "Lepas masker/kacamata gelap, pastikan cahaya cukup, lalu ikuti instruksi (kedipkan mata)."
            )
            .setCancelable(false)
            .setPositiveButton("MULAI") { _, _ -> launchFace("enroll") }
            .show()
    }

    private fun launchFace(mode: String) {
        faceMode = mode
        setBusy(true)
        faceLauncher.launch(Intent(this, FaceActivity::class.java).putExtra(FaceActivity.EXTRA_MODE, mode))
    }

    private fun toJson(v: FloatArray): JSONArray {
        val a = JSONArray()
        for (x in v) a.put(x.toDouble())
        return a
    }

    private fun kirimEnroll(emb: FloatArray, photo: String) {
        setBusy(true)
        tvInfo.text = "> mengirim data wajah..."
        lifecycleScope.launch {
            val body = JSONObject().put("embedding", toJson(emb)).put("photo", photo)
            val r = withContext(Dispatchers.IO) { Api.call(this@MainActivity, "face_enroll.php", "POST", body) }
            setBusy(false)
            if (handleAuth(r)) return@launch
            if (r.ok) {
                showAlert(this@MainActivity, "BERHASIL", r.message)
                loadStatus()
            } else {
                showAlert(this@MainActivity, "GAGAL", r.message)
                if (r.error == "face_exists") loadStatus()
            }
        }
    }

    // ---------- Absen ----------

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
        tvInfo.text = "> mengambil lokasi GPS..."
        val cts = CancellationTokenSource()
        fused.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, cts.token)
            .addOnSuccessListener { loc ->
                if (loc == null) {
                    setBusy(false)
                    tvInfo.text = "> lokasi tidak tersedia, aktifkan GPS lalu coba lagi"
                } else {
                    onLocation(tipe, loc)
                }
            }
            .addOnFailureListener {
                setBusy(false)
                tvInfo.text = "> gagal mengambil lokasi, pastikan GPS aktif"
            }
    }

    private fun locBody(tipe: String, loc: Location, preflight: Boolean, mockApps: String = ""): JSONObject =
        JSONObject()
            .put("tipe", tipe)
            .put("lat", loc.latitude)
            .put("lng", loc.longitude)
            .put("akurasi", loc.accuracy.toDouble())
            .put("is_mock", MockDetector.isMock(loc))
            .put("preflight", preflight)
            .apply { if (mockApps.isNotEmpty()) put("mock_apps", mockApps) }

    private fun onLocation(tipe: String, loc: Location) {
        if (MockDetector.isMock(loc)) {
            handleMock(tipe, loc)
            return
        }
        pendingTipe = tipe
        pendingLoc = loc
        lifecycleScope.launch {
            val r = withContext(Dispatchers.IO) {
                Api.call(this@MainActivity, "absen.php", "POST", locBody(tipe, loc, true))
            }
            if (handleAuth(r)) return@launch
            if (!r.ok) {
                setBusy(false)
                handleFail(r)
                return@launch
            }
            tvInfo.text = "> lokasi OK (${r.json.optString("lokasi")}, ${r.json.optInt("jarak")} m), verifikasi wajah..."
            launchFace("verify")
        }
    }

    private fun handleMock(tipe: String, loc: Location) {
        lifecycleScope.launch {
            val apps = withContext(Dispatchers.IO) { MockDetector.findMockApps(this@MainActivity) }
            setBusy(false)
            tvInfo.text = "> lokasi palsu terdeteksi, absen ditolak"
            MaterialAlertDialogBuilder(this@MainActivity)
                .setTitle("⚠ LOKASI PALSU TERDETEKSI")
                .setMessage(
                    MockDetector.describe(apps) +
                        "\n\nMatikan lewat: Opsi Pengembang › Pilih aplikasi lokasi palsu › pilih \"Tidak ada\" " +
                        "(atau hapus aplikasinya), lalu coba absen lagi.\n\nKejadian ini dicatat dan dilaporkan ke admin."
                )
                .setPositiveButton("MENGERTI", null)
                .show()
            // catat ke server (admin melihat di menu Log)
            withContext(Dispatchers.IO) {
                Api.call(this@MainActivity, "absen.php", "POST", locBody(tipe, loc, false, MockDetector.summary(apps)))
            }
        }
    }

    private fun kirimAbsen(emb: FloatArray, photo: String) {
        val tipe = pendingTipe
        val loc = pendingLoc
        if (tipe == null || loc == null) {
            setBusy(false)
            return
        }
        setBusy(true)
        tvInfo.text = "> memverifikasi & menyimpan absen..."
        lifecycleScope.launch {
            val body = locBody(tipe, loc, false).put("embedding", toJson(emb)).put("photo", photo)
            val r = withContext(Dispatchers.IO) { Api.call(this@MainActivity, "absen.php", "POST", body) }
            if (handleAuth(r)) return@launch
            setBusy(false)
            if (r.ok) {
                pendingTipe = null
                pendingLoc = null
                showAlert(
                    this@MainActivity, "ABSEN BERHASIL",
                    "${r.message}\nJam ${r.json.optString("jam")} · ${r.json.optString("status")} · ${r.json.optInt("jarak")} m dari titik absen"
                )
                loadStatus()
            } else {
                handleFail(r)
            }
        }
    }

    private fun handleFail(r: ApiResult) {
        tvInfo.text = "> " + r.message
        when (r.error) {
            "face_mismatch" -> showAlert(this, "WAJAH TIDAK COCOK", r.message)
            "face_not_enrolled" -> {
                faceEnrolled = false
                loadStatus()
            }
            else -> Toast.makeText(this, r.message, Toast.LENGTH_LONG).show()
        }
    }

    private fun fmtTanggal(iso: String): String = try {
        val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso)!!
        SimpleDateFormat("EEEE, d MMMM yyyy", Locale("id", "ID")).format(d)
    } catch (e: Exception) {
        iso
    }
}
