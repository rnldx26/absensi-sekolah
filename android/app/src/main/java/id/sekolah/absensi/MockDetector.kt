package id.sekolah.absensi

import android.location.Location
import android.os.Build

/** Mendeteksi lokasi yang berasal dari aplikasi mock location (Opsi Pengembang). */
object MockDetector {
    @Suppress("DEPRECATION")
    fun isMock(loc: Location): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            loc.isMock
        } else {
            loc.isFromMockProvider
        }
    }
}
