package id.sekolah.absensi

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.Matrix
import android.graphics.Rect
import android.os.Bundle
import android.os.SystemClock
import android.util.Base64
import android.widget.Button
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.CameraSelector
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.Face
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetector
import com.google.mlkit.vision.face.FaceDetectorOptions
import java.io.ByteArrayOutputStream
import java.util.concurrent.Executors
import kotlin.math.abs

/**
 * Pendaftaran / verifikasi wajah.
 * Alur: wajah menghadap lurus -> instruksi kedip (liveness) -> ambil 3 frame -> embedding rata-rata.
 * Hasil: EXTRA "emb" (FloatArray) dan "photo" (JPEG base64).
 */
class FaceActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_MODE = "mode"        // "enroll" atau "verify"
        const val RESULT_EMB = "emb"
        const val RESULT_PHOTO = "photo"
        private const val NEED_FRAMES = 3
        private const val TIMEOUT_MS = 30000L
    }

    private enum class Stage { ALIGN, CLOSE, OPEN, CAPTURE, DONE }

    private lateinit var previewView: PreviewView
    private lateinit var overlay: FaceOverlayView
    private lateinit var tvStep: TextView

    private var embedder: FaceEmbedder? = null
    private var detector: FaceDetector? = null
    private val exec = Executors.newSingleThreadExecutor()

    private var stage = Stage.ALIGN
    private var steady = 0
    private var lastCapture = 0L
    private val embs = ArrayList<FloatArray>()
    private var photoB64: String? = null
    private val startTime = SystemClock.elapsedRealtime()

    private val permLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            if (granted) startCamera() else fail("Izin kamera diperlukan")
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_face)
        previewView = findViewById(R.id.preview)
        overlay = findViewById(R.id.overlay)
        tvStep = findViewById(R.id.tvStep)

        val enroll = intent.getStringExtra(EXTRA_MODE) == "enroll"
        findViewById<TextView>(R.id.tvTitle).text = if (enroll) "> DAFTAR_WAJAH" else "> VERIFIKASI_WAJAH"
        findViewById<Button>(R.id.btnBatal).setOnClickListener {
            setResult(RESULT_CANCELED)
            finish()
        }

        try {
            embedder = FaceEmbedder(this)
        } catch (e: Exception) {
            fail("Model wajah (${FaceEmbedder.MODEL_FILE}) tidak ditemukan atau tidak valid di aplikasi")
            return
        }
        detector = FaceDetection.getClient(
            FaceDetectorOptions.Builder()
                .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_FAST)
                .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
                .setMinFaceSize(0.3f)
                .build()
        )

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera()
        } else {
            permLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    private fun fail(msg: String) {
        Toast.makeText(this, msg, Toast.LENGTH_LONG).show()
        setResult(RESULT_CANCELED)
        finish()
    }

    private fun startCamera() {
        val future = ProcessCameraProvider.getInstance(this)
        future.addListener({
            val provider = future.get()
            val preview = Preview.Builder().build().also { it.setSurfaceProvider(previewView.surfaceProvider) }
            val analysis = ImageAnalysis.Builder()
                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                .build()
            analysis.setAnalyzer(exec) { proxy -> analyze(proxy) }
            try {
                provider.unbindAll()
                provider.bindToLifecycle(this, CameraSelector.DEFAULT_FRONT_CAMERA, preview, analysis)
                setStep("Hadapkan wajah ke kamera")
            } catch (e: Exception) {
                fail("Kamera depan tidak dapat dibuka")
            }
        }, ContextCompat.getMainExecutor(this))
    }

    @androidx.annotation.OptIn(ExperimentalGetImage::class)
    private fun analyze(proxy: ImageProxy) {
        val media = proxy.image
        val det = detector
        if (media == null || det == null || stage == Stage.DONE) {
            proxy.close()
            return
        }
        val input = InputImage.fromMediaImage(media, proxy.imageInfo.rotationDegrees)
        det.process(input).addOnCompleteListener { t ->
            try {
                if (t.isSuccessful && stage != Stage.DONE) onFaces(t.result, proxy)
            } catch (e: Exception) {
                // frame dilewati
            } finally {
                proxy.close()
            }
        }
    }

    private fun setStep(s: String) {
        tvStep.text = s
    }

    private fun resetTo(msg: String) {
        stage = Stage.ALIGN
        steady = 0
        embs.clear()
        photoB64 = null
        overlay.ok = false
        setStep(msg)
    }

    private fun onFaces(faces: List<Face>, proxy: ImageProxy) {
        if (SystemClock.elapsedRealtime() - startTime > TIMEOUT_MS) {
            fail("Waktu habis, silakan coba lagi")
            return
        }
        if (faces.size != 1) {
            resetTo(if (faces.isEmpty()) "Wajah tidak terdeteksi" else "Pastikan hanya satu wajah di kamera")
            return
        }
        val f = faces[0]
        val rot = proxy.imageInfo.rotationDegrees
        val imgW = if (rot % 180 == 0) proxy.width else proxy.height
        val box = f.boundingBox
        val frontal = abs(f.headEulerAngleY) < 15 && abs(f.headEulerAngleX) < 15 && abs(f.headEulerAngleZ) < 15
        val bigEnough = box.width() > imgW * 0.35f
        if (!bigEnough) { resetTo("Dekatkan wajah ke kamera"); return }
        if (!frontal) { resetTo("Hadapkan wajah lurus ke kamera"); return }
        val l = f.leftEyeOpenProbability ?: -1f
        val r = f.rightEyeOpenProbability ?: -1f
        if (l < 0f || r < 0f) { resetTo("Mata tidak terbaca, perbaiki pencahayaan"); return }
        val open = l > 0.6f && r > 0.6f
        val closed = l < 0.3f && r < 0.3f
        overlay.ok = true

        when (stage) {
            Stage.ALIGN -> {
                if (open) {
                    steady++
                    setStep("Tahan posisi...")
                    if (steady >= 5) {
                        stage = Stage.CLOSE
                        setStep("KEDIPKAN MATA ANDA")
                    }
                } else {
                    steady = 0
                }
            }
            Stage.CLOSE -> if (closed) stage = Stage.OPEN
            Stage.OPEN -> if (open) {
                stage = Stage.CAPTURE
                setStep("Tahan, mengambil data wajah...")
            }
            Stage.CAPTURE -> if (open) capture(proxy, box)
            Stage.DONE -> {}
        }
    }

    private fun capture(proxy: ImageProxy, box: Rect) {
        val now = SystemClock.elapsedRealtime()
        if (now - lastCapture < 250) return
        lastCapture = now

        val bmp = rotate(proxy.toBitmap(), proxy.imageInfo.rotationDegrees)
        val side = (maxOf(box.width(), box.height()) * 1.3f).toInt()
        val x = (box.centerX() - side / 2).coerceIn(0, bmp.width - 1)
        val y = (box.centerY() - side / 2).coerceIn(0, bmp.height - 1)
        val s = minOf(side, bmp.width - x, bmp.height - y)
        if (s < 60) return
        val crop = Bitmap.createBitmap(bmp, x, y, s, s)

        embs.add(embedder!!.embed(crop))
        if (photoB64 == null) photoB64 = toJpegBase64(crop)
        setStep("Mengambil data ${embs.size}/$NEED_FRAMES")
        if (embs.size >= NEED_FRAMES) finishOk()
    }

    private fun finishOk() {
        stage = Stage.DONE
        val dim = embs[0].size
        val avg = FloatArray(dim)
        for (e in embs) for (i in 0 until dim) avg[i] += e[i]
        val out = FaceEmbedder.l2(avg)
        setResult(
            RESULT_OK,
            Intent().putExtra(RESULT_EMB, out).putExtra(RESULT_PHOTO, photoB64)
        )
        finish()
    }

    private fun rotate(b: Bitmap, deg: Int): Bitmap {
        if (deg == 0) return b
        val m = Matrix().apply { postRotate(deg.toFloat()) }
        return Bitmap.createBitmap(b, 0, 0, b.width, b.height, m, true)
    }

    private fun toJpegBase64(b: Bitmap): String {
        val target = if (b.width > 320) Bitmap.createScaledBitmap(b, 320, 320, true) else b
        val bos = ByteArrayOutputStream()
        target.compress(Bitmap.CompressFormat.JPEG, 85, bos)
        return Base64.encodeToString(bos.toByteArray(), Base64.NO_WRAP)
    }

    override fun onDestroy() {
        super.onDestroy()
        exec.shutdown()
        detector?.close()
        embedder?.close()
    }
}
