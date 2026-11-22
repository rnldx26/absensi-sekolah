package id.sekolah.absensi

import android.content.Context
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class ApiResult(val code: Int, val json: JSONObject) {
    val ok: Boolean get() = json.optBoolean("ok", false)
    val message: String get() = json.optString("message", "Terjadi kesalahan")
    val error: String get() = json.optString("error", "")
}

object Api {
    /** Panggil dari background thread (Dispatchers.IO). */
    fun call(ctx: Context, path: String, method: String = "GET", body: JSONObject? = null): ApiResult {
        var conn: HttpURLConnection? = null
        return try {
            conn = URL(BuildConfig.API_BASE + path).openConnection() as HttpURLConnection
            conn.requestMethod = method
            conn.connectTimeout = 15000
            conn.readTimeout = 25000
            conn.setRequestProperty("Accept", "application/json")
            // Identitas perangkat dikirim di setiap request, server mencocokkan dengan data admin
            conn.setRequestProperty("X-Device-Brand", DeviceInfo.brand)
            conn.setRequestProperty("X-Device-Model", DeviceInfo.model)
            conn.setRequestProperty("X-Device-Id", DeviceInfo.androidId(ctx))
            Session.token(ctx)?.let { conn.setRequestProperty("X-Auth-Token", it) }

            if (body != null) {
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                conn.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            }

            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            val text = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""
            val json = try {
                JSONObject(text)
            } catch (e: Exception) {
                JSONObject().put("ok", false).put("message", "Respon server tidak valid ($code)")
            }
            ApiResult(code, json)
        } catch (e: Exception) {
            ApiResult(0, JSONObject().put("ok", false).put("message", "Tidak dapat terhubung ke server"))
        } finally {
            conn?.disconnect()
        }
    }

    /** Unduh file biner (logo). Panggil dari background thread. */
    fun fetchBytes(path: String): ByteArray? {
        var c: HttpURLConnection? = null
        return try {
            c = URL(BuildConfig.API_BASE + path).openConnection() as HttpURLConnection
            c.connectTimeout = 10000
            c.readTimeout = 15000
            if (c.responseCode in 200..299) c.inputStream.use { it.readBytes() } else null
        } catch (e: Exception) {
            null
        } finally {
            c?.disconnect()
        }
    }
}
