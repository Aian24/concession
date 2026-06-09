# Concession App - Android APK

This folder contains the source code for the **Concession System Android App**.  
It wraps the web-based Concession System in a native Android WebView, making it feel like a real app.

---

## 🔧 Setup

### 1. Set Your Website URL

Open `app/src/main/java/com/concession/app/MainActivity.java` and change this line:

```java
private static final String WEB_URL = "https://your-domain.com/Concession/";
```

Replace `your-domain.com` with your actual live domain (Bluehost URL).

### 2. Add Your App Icon

Copy your `icon-192.png` to these folders (resize as needed):
- `app/src/main/res/mipmap-hdpi/ic_launcher.png` (72x72)
- `app/src/main/res/mipmap-xhdpi/ic_launcher.png` (96x96)
- `app/src/main/res/mipmap-xxhdpi/ic_launcher.png` (144x144)
- `app/src/main/res/mipmap-xxxhdpi/ic_launcher.png` (192x192)

### 3. Build the APK

#### Option A: Android Studio (Recommended)
1. Download [Android Studio](https://developer.android.com/studio)
2. Open the `mobile/` folder as a project
3. Go to **Build → Build Bundle(s)/APK(s) → Build APK(s)**
4. The APK will be in `app/build/outputs/apk/`

#### Option B: Online APK Builder
1. Use a free service like [AppsGeyser](https://appsgeyser.com) or [WebIntoApp](https://webintoapp.com)
2. Enter your website URL
3. Upload your logo icon
4. Download the generated APK

#### Option C: Command Line (requires JDK 17+)
```bash
cd mobile
./gradlew assembleDebug
```

### 4. Deploy the APK

Copy the built APK file to:
```
mobile/concession-app.apk
```

This makes it available for download via the **"Install App"** button on the login page.

---

## 📱 Features

- Full-screen WebView (no address bar)
- Dark status bar matching the app theme
- Splash screen with loading indicator
- File upload support (avatars, pullout images)
- Hardware back button navigation
- Cookie persistence (stays logged in)
- Camera access (barcode scanner)

---

## ⚡ Quick Alternative: PWA Install

The app also includes a **PWA (Progressive Web App)** setup. Users can install the app directly from Chrome without downloading an APK:

1. Open the website in Chrome on Android
2. Tap the **"Install App"** button on the login page
3. Chrome will add the app to the home screen

> **Note:** PWA install requires HTTPS on the production server.
