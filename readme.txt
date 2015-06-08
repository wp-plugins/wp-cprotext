=== Plugin Name ===
Contributors: askadice
Donate link:
Tags: copyright, text, content protection, no right click, blog protection, copy protection, copy protect, copy, paste, protection, cprotext
Requires at least: 3.9.0
Tested up to: 4.2.2
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

CPROTEXT protects your texts from unauthorized and fraudulent copy. The protected texts are
immune to in-browser copy/paste and HTML code parsing.

== Description ==

CPROTEXT is an online service for copyright protection that you
can test via the example page of the [CPROTEXT website](https://www.cprotext.com
"CPROTEXT: Copyright protection for online texts").

WP-CPROTEXT protects your texts published on a WordPress based website
from any kind of digital copy, being in-browser copy/paste or HTML code parsing
by web crawlers. When you decide to protect and publish a text in
WordPress, it is first submitted to the CPROTEXT online service to be
processed. The returned data are then stored in your WordPress database
in addition to the original text. These data are used to display a copy
protected version of your original text.

Once stored, __the protected text data are forever yours, independently
of the CPROTEXT online service__. You can then choose to enable or
disable protection at will.

As explained in the [CPROTEXT F.A.Q.](https://www.cprotext.com/en/faq.html), the
protected text data returned by the CPROTEXT online service is only
standard HTML and CSS code. No JavaScript code is required
to make this protection effective. Therefore, __contrary to other copy protection plugins, the
CPROTEXT protection is engraved in the web page and can not be removed__
by disabling browser JavaScript support. Moreover, attempts to alter the protected text
data would result in displaying a randomly modified text.

To improve the SEO rank of your text web page, you are able to insert a
placeholder text. This placeholder will be available to web crawlers such as search
engines. It can either be an abstract of your content, the first lines
of your text, or whatever keywords you would like to expose to
search engines so that your content is efficiently referenced. This
placeholder text is also used as a failover for older browsers failing to
comply to basic web standards.

The WP-CPROTEXT Settings page offers you several options:

* font to use to display your text ( cf. [CPROTEXT
F.A.Q.](https://www.cprotext.com/en/faq.html) )
* reader notification about the text protection

== Installation ==

1. Download the zip file
1. Upload and extract the content of the zip file in your `/wp-content/plugins/` directory
1. Activate the WP-CPROTEXT plugin through the 'Plugins' menu in WordPress
1. Enjoy !

== Frequently Asked Questions ==

Check the [CPROTEXT website](https://www.cprotext.com) for the F.A.Q. related
to the CPROTEXT online service.

*  Why are you doing this ? Text obfuscation is wrong !

   While the nature of the web is to share information widely, some decide
   that they want to keep control over their contents.

   If content protection is not a priority, then don't use such protection.
   But those who wish to protect their texts should have the choice of a real solution.

   Other available solutions give a false sense of protection since they can
   be easily bypassed. Moreover they usually are very intrusive by altering the
   browser functionalities of the website visitors, such as disabling the
   context menu (right click).
   With CPROTEXT, authors can protect their contents with a truly efficient
   solution without annoying their website visitors.

   The only valid reason against text obfuscation is the loss of accessibility.
   That's why we strongly advise every CPROTEXT user to publish their protected text
   with an audio version such as in the CPROTEXT [example](https://www.cprotext.com/en/example.html) page.

* Why is this limited to text ? What about images copy protection ?

  Copying an image published on the web is as simple as making a screen capture.
  Contrary to texts which would require additional OCR processing, a picture
  displayed on a screen can be copied as simply as hitting a key of your
  keyboard, whatever the copy protections in place.

  Therefore, a truly efficient copy protection of images is not possible when
  it aims at website visitors using a graphical browser.

  Protection from web crawlers is something else, and could be integrated in
  CPROTEXT. But, as of today, nobody expressed any interest for such a feature.

  The only advisable solutions are publishing low resolution of your work and/or
  use watermarks.


== Screenshots ==

== Changelog ==
= 1.2.0 =

* add "no notification" and "stealth notification" options
* fix title only post updates
* fix issues with simple/double quotes and anti-slashes
* fix ie8 support related issues

= 1.1.0 =
apply WordPress good practices for AJAX use
 
= 1.0.0 =
Initial release

