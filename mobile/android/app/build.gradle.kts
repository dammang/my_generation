import java.util.Properties

// Signing credentials, kept out of the repository. Created by hand with
// keytool; see the README. Absent on a fresh clone and in CI, which is why
// every use of it below is guarded rather than assumed.
val keystoreProperties = Properties().apply {
    val file = rootProject.file("key.properties")
    if (file.exists()) {
        file.inputStream().use { load(it) }
    }
}
val hasReleaseKeystore = keystoreProperties.containsKey("storeFile")

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
    id("com.google.firebase.crashlytics")
}

android {
    namespace = "com.khanggui"
    // 37 rather than flutter.compileSdkVersion (36): flutter_secure_storage
    // ships an AAR that requires API 37 to compile against. The token store is
    // not optional, so the platform version follows it.
    compileSdk = 37
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.khanggui"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // 23 rather than flutter.minSdkVersion: firebase_auth and
        // google_sign_in both require it, and the build fails at manifest
        // merge rather than anywhere informative if it is lower.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (hasReleaseKeystore) {
            create("release") {
                storeFile = file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
            }
        }
    }

    buildTypes {
        release {
            if (hasReleaseKeystore) {
                signingConfig = signingConfigs.getByName("release")
            } else {
                // Falls back so `flutter run --release` still works on a clone
                // without the keystore — but says so, loudly. A release signed
                // with the debug key has the debug certificate fingerprint,
                // and Google sign-in is registered against the release one, so
                // it would install fine and fail at the sign-in button.
                logger.warn(
                    "WARNING: no android/key.properties — signing this release with the DEBUG key. " +
                        "Do not ship this build: Google sign-in will fail against the release SHA-1."
                )
                signingConfig = signingConfigs.getByName("debug")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
