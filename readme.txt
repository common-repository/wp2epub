=== wp2epub ===
Contributors: tcrouzet
Tags: wp2epub, epub, html, ebook, ipad, iphone, word, export, archive, zip, crouzet, publish, save, backup
Requires at least: 2.7
Tested up to: 3.5.1
Stable tag: 0.65

wp2epub generate ePub files directly from WordPress.

== Description ==

wp2epub generate epub files, ready to publish, for iPad, iPhone and other readers. Just choose the tags, categories or dates to export. It's done. You are now a bloguer and a writer. wp2epub also export in html, and then you can open with a wordprocessor to convert into PDF or other formats. A good way to backup your blog. Possible integration on each post.

== Screenshots ==

1. The wp2epub backend, one epub declared.

== Installation ==

Upload the wp2epub plugin to your blog, activate it, then go to setting.

== Changelog ==

= 0.65 =

* Still an installation bug.

= 0.64 =

* Database creation bug.

= 0.63 =

* Post integration was slowing wordpress. You have to use links or microcode instead.
* Microcode integration.
* Speed optimisation.

= 0.62 =

* Settings optimisation.

= 0.61 =

* Frontal integration. Possible export button on each post. Possible link.
* Tidy parsing, better html
* ePub cleaning
* test on my blog: epub size 23Mo...

= 0.60 =

* 2013/1/18
* improve memoty management (use MYSQL vars instead of ram). Possible to export very big blog.
* new sort mode
* cover image ok
* working with proxy
* log process to help debug
* new interface
* paypal form

= 0.51 =

* Comments OK (but risk of memory overflow)
* ISBN

= 0.50 =

* Just to show the gap between the 0.24

= 0.25 =

* Introduction post
* Source at the bottom of post
* Better table of contents
* Far less bugs

= 0.24 =

* Langage tag ok

= 0.23 =

* No dates
* Less french
* Choose style sheet

= 0.22 =

* Better with bad file_get_contents()

= 0.21 =

* Big bug on PHP 4 solved (in wp-admin, you have to delete de .htaccess and replace the index.php with an original one)

= 0.20 =

* Valid epub for http://threepress.org/document/epub-validate/

= 0.19 =

* Exclude date.
* Sub title on text cover.
* Copyright page.
* Colophon page.
* New form.
* Working on PHP 4.
* Autonomus CSS.
* US dates.

= 0.18 =

* Less bugs...

= 0.17 =

* More simple database...

= 0.16 =

* Filter by date, works without tags, new interface...

= 0.15 =

* Export htm with images in a zip file... ok for MS Word.

= 0.14 =

* Export htm working... file ok for MS Word, no picture support.

= 0.13 =

* First version.