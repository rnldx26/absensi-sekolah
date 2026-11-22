package id.sekolah.absensi

import android.content.Context
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import org.json.JSONObject

fun showAlert(ctx: Context, title: String, msg: String) {
    MaterialAlertDialogBuilder(ctx)
        .setTitle(title)
        .setMessage(msg)
        .setPositiveButton("OK", null)
        .show()
}

/** Isi pop-up ketika perangkat tidak sama dengan yang didaftarkan admin. */
fun deviceMessage(j: JSONObject): String {
    val alasan = j.optString("alasan").removePrefix("login: ")
    return "Akun ini terdaftar untuk perangkat:\n  ${j.optString("terdaftar")}\n\n" +
        "Perangkat yang sedang dipakai:\n  ${j.optString("dipakai")}\n\n" +
        (if (alasan.isNotEmpty()) "Keterangan: $alasan\n\n" else "") +
        "Login hanya bisa dari perangkat yang didaftarkan admin. Hubungi admin untuk mengganti perangkat."
}
