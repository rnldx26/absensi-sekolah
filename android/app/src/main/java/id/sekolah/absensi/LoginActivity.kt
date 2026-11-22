package id.sekolah.absensi

import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.ImageView
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.textfield.TextInputEditText
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

class LoginActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (Session.token(this) != null) {
            goMain()
            return
        }
        setContentView(R.layout.activity_login)

        val etNip = findViewById<TextInputEditText>(R.id.etNip)
        val etPass = findViewById<TextInputEditText>(R.id.etPass)
        val btn = findViewById<Button>(R.id.btnLogin)
        val progress = findViewById<ProgressBar>(R.id.progress)
        val ivLogo = findViewById<ImageView>(R.id.ivLogo)
        val tvInstansi = findViewById<TextView>(R.id.tvInstansi)

        findViewById<TextView>(R.id.tvDevice).text =
            "# info perangkat (untuk didaftarkan admin)\nbrand : ${DeviceInfo.brand}\nmodel : ${DeviceInfo.model}"

        Session.brand(this)?.takeIf { it.isNotEmpty() }?.let {
            tvInstansi.text = it
            tvInstansi.visibility = View.VISIBLE
        }
        loadBranding(ivLogo, tvInstansi)

        intent.getStringExtra("msg")?.let {
            showAlert(this, intent.getStringExtra("title") ?: "PERHATIAN", it)
        }

        btn.setOnClickListener {
            val nip = etNip.text.toString().trim()
            val pass = etPass.text.toString()
            if (nip.isEmpty() || pass.isEmpty()) {
                Toast.makeText(this, "NIP dan password wajib diisi", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            btn.isEnabled = false
            progress.visibility = View.VISIBLE
            lifecycleScope.launch {
                val body = JSONObject()
                    .put("nip", nip)
                    .put("password", pass)
                    .put("brand", DeviceInfo.brand)
                    .put("model", DeviceInfo.model)
                    .put("android_id", DeviceInfo.androidId(this@LoginActivity))
                val r = withContext(Dispatchers.IO) {
                    Api.call(this@LoginActivity, "login.php", "POST", body)
                }
                btn.isEnabled = true
                progress.visibility = View.GONE
                when {
                    r.ok -> {
                        Session.save(this@LoginActivity, r.json.getString("token"), r.json.optString("nama"))
                        goMain()
                    }
                    r.error == "device_mismatch" ->
                        showAlert(this@LoginActivity, "⚠ PERANGKAT TIDAK TERDAFTAR", deviceMessage(r.json))
                    else -> Toast.makeText(this@LoginActivity, r.message, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    private fun loadBranding(iv: ImageView, tv: TextView) {
        lifecycleScope.launch {
            val r = withContext(Dispatchers.IO) { Api.call(this@LoginActivity, "branding.php") }
            if (!r.ok) return@launch
            if (!r.json.optBoolean("tampil", true)) {
                tv.visibility = View.GONE
                iv.visibility = View.GONE
                Session.saveBrand(this@LoginActivity, null)
                return@launch
            }
            val nama = r.json.optString("nama")
            Session.saveBrand(this@LoginActivity, nama)
            if (nama.isNotEmpty()) {
                tv.text = nama
                tv.visibility = View.VISIBLE
            }
            if (r.json.optBoolean("logo")) {
                val bmp = withContext(Dispatchers.IO) { Api.fetchBytes("logo.php")?.let { decodeSampled(it, 512) } }
                if (bmp != null) {
                    iv.setImageBitmap(bmp)
                    iv.visibility = View.VISIBLE
                }
            }
        }
    }

    private fun decodeSampled(b: ByteArray, max: Int): Bitmap? {
        val o = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeByteArray(b, 0, b.size, o)
        var s = 1
        while (o.outWidth / s > max || o.outHeight / s > max) s *= 2
        return BitmapFactory.decodeByteArray(b, 0, b.size, BitmapFactory.Options().apply { inSampleSize = s })
    }

    private fun goMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}
