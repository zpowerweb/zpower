# ZPower Slider

Standalone responsive WordPress slider plugin extracted from the ZPower Website Toolbox slider module.

## Features

- Custom post type: `zpower_slider`
- Shortcode output: `[zpower_slider id="123"]`
- Desktop and mobile image support
- Manual image sorting
- Autoplay, arrows, dots, and style settings
- Automatic post slider mode
- Responsive fixed-height settings
- Bundled Swiper 11.0.5 assets, no CDN dependency
- Traditional Chinese default UI with English UI for `en*` locales

## Repository Structure

- `zpower-slider.php`: main plugin file
- `assets/js/`: WordPress admin helper scripts
- `assets/vendor/swiper/`: bundled Swiper frontend assets
- `languages/`: translation template and English language files

## Installation

1. Download or clone this repository.
2. Put the plugin files in a WordPress plugin folder named `zpower-slider`.
3. Activate `ZPower Slider` from WordPress admin > Plugins.
4. Manage sliders from the `Sliders` admin menu.
5. Place a slider on a page with `[zpower_slider id="123"]`.

## Language

The plugin loads the `zpower-slider` text domain from `languages/`.

- Default UI: Traditional Chinese
- English UI: enabled when the WordPress site or user locale starts with `en`
- Included files: `zpower-slider-en_US.po`, `zpower-slider-en_US.mo`, `zpower-slider.pot`

## Notes

This standalone plugin keeps the same post type and shortcode names as the toolbox module. Do not enable both the toolbox slider module and this standalone plugin on the same site unless you intentionally want this plugin to own the same `zpower_slider` post type and `[zpower_slider]` shortcode.

---

# ZPower Slider 輪播圖外掛

這是從 ZPower Website Toolbox 輪播圖模組獨立出來的 WordPress 響應式輪播圖外掛。

## 功能

- 自訂文章類型：`zpower_slider`
- 短代碼輸出：`[zpower_slider id="123"]`
- 支援桌機與手機圖片
- 支援手動圖片排序
- 支援自動播放、箭頭、分頁點與樣式設定
- 支援文章自動輪播模式
- 支援響應式固定高度設定
- 內建 Swiper 11.0.5，不依賴 CDN
- 預設繁體中文介面，`en*` 語系自動顯示英文介面

## Repository 結構

- `zpower-slider.php`：外掛主程式
- `assets/js/`：WordPress 後台輔助腳本
- `assets/vendor/swiper/`：內建 Swiper 前台資源
- `languages/`：翻譯範本與英文語言檔

## 安裝方式

1. 下載或 clone 這個 repository。
2. 將外掛檔案放到 WordPress 外掛資料夾，資料夾名稱請使用 `zpower-slider`。
3. 到 WordPress 後台 > 外掛，啟用 `ZPower Slider`。
4. 從後台 `輪播圖` 選單管理輪播內容。
5. 使用 `[zpower_slider id="123"]` 將輪播放到頁面。

## 語言

外掛會從 `languages/` 載入 `zpower-slider` text domain。

- 預設介面：繁體中文
- 英文介面：當 WordPress 站台或使用者語系以 `en` 開頭時啟用
- 內含檔案：`zpower-slider-en_US.po`、`zpower-slider-en_US.mo`、`zpower-slider.pot`

## 注意事項

這個獨立外掛保留與工具箱輪播模組相同的 post type 和 shortcode。除非你明確要讓此獨立外掛接管同一組 `zpower_slider` post type 與 `[zpower_slider]` shortcode，否則不建議同一網站同時啟用工具箱輪播模組與此獨立外掛。
