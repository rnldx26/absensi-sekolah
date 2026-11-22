package id.sekolah.absensi

import android.content.Context

object Session {
    private fun p(c: Context) = c.getSharedPreferences("sesi", Context.MODE_PRIVATE)

    fun token(c: Context): String? = p(c).getString("token", null)

    fun save(c: Context, token: String, nama: String) {
        p(c).edit().putString("token", token).putString("nama", nama).apply()
    }

    fun clear(c: Context) {
        p(c).edit().clear().apply()
    }
}
