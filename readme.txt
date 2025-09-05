=== AI Blogger ===
Contributors: hridoyvaraby
Donate link: https://varabit.com/
Tags: ai, content generation, blog, seo, groq
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Effortlessly generate high-quality AI-powered blog posts with AI Blogger, integrating Groq's cutting-edge language models.

== Description ==

AI Blogger is an advanced AI-powered content generation plugin for WordPress, utilizing Groq’s high-performance LLM inference API. It enables effortless blog post creation, customizable templates, and multi-language support, streamlining your content workflow.

**Features:**

- Direct integration with Groq's cloud API
- AI-powered blog post generation
- Customizable content templates
- Multiple LLM model support
- SEO-friendly content suggestions
- One-click content generation
- Customizable tone and style options
- Multi-language support
- Ability to select any model from Groq API

== External services ==

This plugin connects to the Groq API to generate blog posts using AI. 
It sends the blog title and context as a request to the API and receives AI-generated content.

- **Service Name:** Groq API
- **Data Sent:** Blog title, user prompt
- **Data Received:** AI-generated blog post
- **Privacy Policy:** [Groq Privacy Policy](https://console.groq.com/terms)
- **Terms of Service:** [Groq Terms of Use](https://console.groq.com/terms)

This plugin also connects to the Pexels API to fetch relevant images for blog posts.
It sends the blog title or keywords as a search query and receives image results.

- **Service Name:** Pexels API
- **Data Sent:** Search keywords based on blog content
- **Data Received:** Relevant images for blog posts
- **Privacy Policy:** [Pexels Privacy Policy](https://www.pexels.com/privacy-policy/)
- **Terms of Service:** [Pexels Terms of Service](https://www.pexels.com/terms-of-service/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-blogger` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure your API keys in Settings → AI Blogger

== Frequently Asked Questions ==

= How do I get a Groq API key? =
You can obtain a free Groq API key from the [Groq Cloud Console](https://console.groq.com).

= Can I customize the content generated? =
Yes! AI Blogger allows you to adjust the tone, style, and structure of generated content through its settings.

= Does this plugin support multiple AI models? =
Yes, it supports various state-of-the-art LLMs such as Mixtral-8x7b, Llama2-70b, and Gemma-7b, etc.

== Screenshots ==
1. Settings page - assets/screenshots/Screenshot1.png
2. API configuration - assets/screenshots/Screenshot2.png
3. Post generation interface - assets/screenshots/Screenshot3.png
4. Content preview - assets/screenshots/Screenshot4.png

== Changelog ==

= 1.0.6 - 2025-09-05 =
- Feature: Added support for all models from the Groq API.
- Tweak: The model selection dropdown in the settings now dynamically fetches the latest models from Groq.