package id.sekolah.absensi

import android.app.AppOpsManager
import android.content.Context
import android.content.pm.ApplicationInfo
import android.content.pm.PackageManager
import android.location.Location
import android.os.Build

data class MockApp(val label: String, val pkg: String, val selected: Boolean)

/** Mendeteksi lokasi palsu (Opsi Pengembang) dan menyebutkan aplikasi pemalsunya. */
object MockDetector {

    @Suppress("DEPRECATION")
    fun isMock(loc: Location): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            loc.isMock
        } else {
            loc.isFromMockProvider
        }
    }

    /**
     * Daftar aplikasi pengguna yang meminta izin ACCESS_MOCK_LOCATION.
     * selected = true jika aplikasi tsb dipilih sebagai "aplikasi lokasi palsu" di Opsi Pengembang.
     */
    @Suppress("DEPRECATION")
    fun findMockApps(ctx: Context): List<MockApp> {
        val pm = ctx.packageManager
        val ops = ctx.getSystemService(Context.APP_OPS_SERVICE) as AppOpsManager
        val result = ArrayList<MockApp>()
        val pkgs = try {
            if (Build.VERSION.SDK_INT >= 33) {
                pm.getInstalledPackages(PackageManager.PackageInfoFlags.of(PackageManager.GET_PERMISSIONS.toLong()))
            } else {
                pm.getInstalledPackages(PackageManager.GET_PERMISSIONS)
            }
        } catch (e: Exception) {
            return result
        }
        for (pi in pkgs) {
            val ai = pi.applicationInfo ?: continue
            if (pi.packageName == ctx.packageName) continue
            val isSystem = (ai.flags and ApplicationInfo.FLAG_SYSTEM) != 0 &&
                (ai.flags and ApplicationInfo.FLAG_UPDATED_SYSTEM_APP) == 0
            if (isSystem) continue
            val req = pi.requestedPermissions ?: continue
            if (!req.contains("android.permission.ACCESS_MOCK_LOCATION")) continue
            val selected = try {
                val mode = if (Build.VERSION.SDK_INT >= 29) {
                    ops.unsafeCheckOpNoThrow(AppOpsManager.OPSTR_MOCK_LOCATION, ai.uid, pi.packageName)
                } else {
                    ops.checkOpNoThrow(AppOpsManager.OPSTR_MOCK_LOCATION, ai.uid, pi.packageName)
                }
                mode == AppOpsManager.MODE_ALLOWED
            } catch (e: Exception) {
                false
            }
            result.add(MockApp(pm.getApplicationLabel(ai).toString(), pi.packageName, selected))
        }
        return result.sortedByDescending { it.selected }
    }

    /** Teks untuk pop-up. */
    fun describe(apps: List<MockApp>): String {
        val sel = apps.filter { it.selected }
        return when {
            sel.isNotEmpty() ->
                "Aplikasi mock location yang aktif:\n" + sel.joinToString("\n") { "• ${it.label}\n  (${it.pkg})" }
            apps.isNotEmpty() ->
                "Aplikasi yang sedang aktif tidak dapat dipastikan. Aplikasi terpasang yang berpotensi memalsukan lokasi:\n" +
                    apps.take(6).joinToString("\n") { "• ${it.label}\n  (${it.pkg})" }
            else -> "Nama aplikasi pemalsu lokasi tidak dapat diidentifikasi."
        }
    }

    /** Ringkasan singkat untuk dicatat di server. */
    fun summary(apps: List<MockApp>): String {
        val sel = apps.filter { it.selected }
        val list = sel.ifEmpty { apps.take(5) }
        if (list.isEmpty()) return ""
        return (if (sel.isEmpty()) "kandidat: " else "") + list.joinToString("; ") { "${it.label} <${it.pkg}>" }
    }
}
