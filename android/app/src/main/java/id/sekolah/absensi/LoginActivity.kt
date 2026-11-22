package id.sekolah.absensi

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Button
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

        intent.getStringExtra("msg")?.let { Toast.makeText(this, it, Toast.LENGTH_LONG).show() }

        val etNip = findViewById<TextInputEditText>(R.id.etNip)
        val etPass = findViewById<TextInputEditText>(R.id.etPass)
        val btn = findViewById<Button>(R.id.btnLogin)
        val progress = findViewById<ProgressBar>(R.id.progress)
        findViewById<TextView>(R.id.tvDevice).text =
            "Info perangkat (untuk didaftarkan admin):\nBrand: ${DeviceInfo.brand}\nModel: ${DeviceInfo.model}"

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
                if (r.ok) {
                    Session.save(this@LoginActivity, r.json.getString("token"), r.json.optString("nama"))
                    goMain()
                } else {
                    Toast.makeText(this@LoginActivity, r.message, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    private fun goMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}
