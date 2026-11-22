plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "id.sekolah.absensi"
    compileSdk = 34

    defaultConfig {
        applicationId = "id.sekolah.absensi"
        minSdk = 24
        targetSdk = 34
        versionCode = 2
        versionName = "2.0"

        // GANTI dengan alamat folder api di server Anda (wajib HTTPS, akhiri dengan /)
        buildConfigField("String", "API_BASE", "\"https://domain-anda.sch.id/absensi/api/\"")
    }

    buildFeatures { buildConfig = true }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.4")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
    implementation("com.google.android.gms:play-services-location:21.3.0")

    // Kamera + deteksi wajah + embedding wajah
    val camerax = "1.3.4"
    implementation("androidx.camera:camera-core:$camerax")
    implementation("androidx.camera:camera-camera2:$camerax")
    implementation("androidx.camera:camera-lifecycle:$camerax")
    implementation("androidx.camera:camera-view:$camerax")
    implementation("com.google.mlkit:face-detection:16.1.6")
    implementation("org.tensorflow:tensorflow-lite:2.14.0")
}
