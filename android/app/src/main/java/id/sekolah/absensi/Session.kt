package id.sekolah.absensi

import android.content.Context

object Session {
    private fun p(c: Context) = c.getSharedPreferences("sesi", Context.MODE_PRIVATE)

    fun token(c: Context): String? = p(c).getString("token", null)

    fun save(c: Context, token: String, nama: String) {
        p(c).edit().putString("token", token).putString("nama", nama).apply()
    }

    /** Menghapus sesi login, nama instansi (cache tampilan login) tetap disimpan. */
    fun clear(c: Context) {
        val brand = brand(c)
        p(c).edit().clear().apply()
        saveBrand(c, brand)
    }

    fun brand(c: Context): String? = p(c).getString("brand", null)

    fun saveBrand(c: Context, name: String?) {
        p(c).edit().putString("brand", name).apply()
    }
}
