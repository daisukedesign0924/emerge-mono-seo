=== Emerge Mono SEO ===
Contributors: emergemono
Stable tag: 0.5.4
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
License: GPLv2 or later

Integrated SEO, local search, AI discovery and structured-data management for WordPress.

== Description ==

Emerge Mono SEO works as a standalone WordPress plugin and integrates with the document context provided by Emerge Mono Core or Journal when either product is active.

== Changelog ==

= 0.5.4 =
* Added GitHub Releases update discovery and SHA-256 package verification.
* Added shared stable, beta and alpha update channel controls.

= 0.5.0 =
* Rebuilt the SEO / AI settings UX while preserving the Emerge Mono dashboard design.
* Separated SEO foundation, content quality, MEO readiness and AI readiness instead of hiding them in one score.
* Added prioritized next actions, clearer setting descriptions and honest external-connection states.
* Improved responsive navigation, settings hierarchy, unsaved-change feedback and search preview guidance.

= 0.4.3 =
* Fixed the SEO / AI page losing the Core sidebar when Journal is installed.
* The Core shell and its styles are now used whenever Emerge Mono Core is available.

= 0.4.2 =
* CoreとJournalのどちらでもSEO画面をシリーズ共通ダッシュボード内に表示。
* Journal内でSEO画面だけ左へずれる余白の競合を修正。

= 0.4.1 =
* Fixed compact Core and Journal editor panels overflowing narrow sidebars.

= 0.4.0 =
* Added page audit scores, search previews, social settings and schema selection.
* Added optional BYOK AI suggestions with explicit per-field review and apply controls.
* Expanded dedicated editor integration for Core and Journal, including Journal pages.

= 0.3.1 =
* ページ別のSNSシェア画像をWordPressメディアライブラリから選択可能に変更。

= 0.3.0 =
* Added centralized per-page SEO management and Core/Journal editor integration.
* Added safe import and restore for Yoast SEO, AIOSEO, SEOPress, and The SEO Framework.

= 0.2.0 =
* Rank Mathのページ別SEO情報を非破壊で読み込む機能を追加。
* 読み込み前へ戻すバックアップ機能を追加。
* canonical URL、SNSシェア画像、フォーカスキーワードの編集に対応。

= 0.1.0 =
* SEOタイトル、説明、canonical、robots、OGPを追加。
* Organization、LocalBusiness、WebSite、WebPage、Article、CreativeWork、BreadcrumbのJSON-LDに対応。
* llms.txt、XML Sitemap案内、AIクローラー制御を追加。
* Coreの独立HTML出力とJournalの専用投稿タイプに対応。
* 主要SEOプラグインとの重複出力保護を追加。
