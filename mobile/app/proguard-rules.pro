# ProGuard rules for Concession App
# Keep WebView JavaScript interface
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}
-keepattributes JavascriptInterface

# Keep the main activity
-keep class com.concession.app.** { *; }
