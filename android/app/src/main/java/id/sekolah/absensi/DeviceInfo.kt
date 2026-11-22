package id.sekolah.absensi

import android.content.Context
import android.os.Build
import android.provider.Settings

/** Brand & model sama dengan ro.product.brand / ro.product.model di build.prop. */
object DeviceInfo {
    val brand: String get() = (Build.BRAND ?: "").trim()
    val model: String get() = (Build.MODEL ?: "").trim()

    fun androidId(ctx: Context): String =
        Settings.Secure.getString(ctx.contentResolver, Settings.Secure.ANDROID_ID) ?: ""
}
