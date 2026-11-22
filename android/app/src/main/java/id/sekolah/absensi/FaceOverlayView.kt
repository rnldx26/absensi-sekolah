package id.sekolah.absensi

import android.content.Context
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.PorterDuff
import android.graphics.PorterDuffXfermode
import android.graphics.RectF
import android.util.AttributeSet
import android.view.View

/** Bingkai oval kuning untuk memandu posisi wajah (gaya terminal). */
class FaceOverlayView @JvmOverloads constructor(context: Context, attrs: AttributeSet? = null) : View(context, attrs) {

    var ok: Boolean = false
        set(v) {
            field = v
            invalidate()
        }

    private val dim = Paint().apply { color = 0xCC000000.toInt() }
    private val clear = Paint(Paint.ANTI_ALIAS_FLAG).apply { xfermode = PorterDuffXfermode(PorterDuff.Mode.CLEAR) }
    private val stroke = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeWidth = 6f
    }

    init {
        setLayerType(LAYER_TYPE_SOFTWARE, null)
    }

    override fun onDraw(canvas: Canvas) {
        val w = width.toFloat()
        val h = height.toFloat()
        val rw = w * 0.72f
        val rh = rw * 1.25f
        val cx = w / 2f
        val cy = h * 0.42f
        val oval = RectF(cx - rw / 2, cy - rh / 2, cx + rw / 2, cy + rh / 2)
        canvas.drawRect(0f, 0f, w, h, dim)
        canvas.drawOval(oval, clear)
        stroke.color = if (ok) 0xFF4ADE80.toInt() else 0xFFFFD400.toInt()
        canvas.drawOval(oval, stroke)
    }
}
