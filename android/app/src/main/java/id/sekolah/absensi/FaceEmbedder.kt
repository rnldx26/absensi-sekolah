package id.sekolah.absensi

import android.content.Context
import android.graphics.Bitmap
import org.tensorflow.lite.Interpreter
import java.nio.ByteBuffer
import java.nio.ByteOrder
import kotlin.math.sqrt

/**
 * Mengubah foto wajah (sudah dicrop) menjadi vektor embedding memakai model MobileFaceNet TFLite.
 * Model: float32, input [1,112,112,3], output [1,N] (N = 128 atau 192, terdeteksi otomatis).
 * Jika model Anda memakai normalisasi lain, ubah MEAN dan STD di bawah.
 */
class FaceEmbedder(ctx: Context) : AutoCloseable {
    private val interpreter: Interpreter
    private val inSize: Int
    private val outDim: Int

    init {
        val bytes = ctx.assets.open(MODEL_FILE).use { it.readBytes() }
        val buf = ByteBuffer.allocateDirect(bytes.size).order(ByteOrder.nativeOrder())
        buf.put(bytes)
        buf.rewind()
        interpreter = Interpreter(buf, Interpreter.Options().setNumThreads(2))
        inSize = interpreter.getInputTensor(0).shape()[1]
        outDim = interpreter.getOutputTensor(0).shape().last()
    }

    fun embed(face: Bitmap): FloatArray {
        val scaled = Bitmap.createScaledBitmap(face, inSize, inSize, true)
        val px = IntArray(inSize * inSize)
        scaled.getPixels(px, 0, inSize, 0, 0, inSize, inSize)
        val input = ByteBuffer.allocateDirect(4 * inSize * inSize * 3).order(ByteOrder.nativeOrder())
        for (p in px) {
            input.putFloat((((p shr 16) and 0xFF) - MEAN) / STD)
            input.putFloat((((p shr 8) and 0xFF) - MEAN) / STD)
            input.putFloat(((p and 0xFF) - MEAN) / STD)
        }
        input.rewind()
        val out = Array(1) { FloatArray(outDim) }
        interpreter.run(input, out)
        return l2(out[0])
    }

    override fun close() {
        interpreter.close()
    }

    companion object {
        const val MODEL_FILE = "mobile_face_net.tflite"
        private const val MEAN = 128f
        private const val STD = 128f

        fun l2(v: FloatArray): FloatArray {
            var n = 0f
            for (x in v) n += x * x
            val d = sqrt(n)
            if (d < 1e-9f) return v
            return FloatArray(v.size) { v[it] / d }
        }
    }
}
