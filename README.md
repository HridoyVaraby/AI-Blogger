# AI Blogger - WordPress Plugin

![Plugin Version](https://img.shields.io/badge/Version-1.0.6-blue)
![WordPress Tested](https://img.shields.io/badge/WordPress-6.8+-green)

**Author:** [Hridoy Varaby](https://github.com/HridoyVaraby) | [Varabit](https://varabit.com)

A powerful AI content generation plugin for WordPress that leverages Groq's ultra-fast LLM inference platform.

## Features

- Direct integration with Groq's cloud API
- AI-powered blog post generation
- Customizable content templates
- Multiple LLM model support
- SEO-friendly content suggestions
- One-click content generation
- Customizable tone and style options
- Multi-language support
- Ability to select any model from Groq API

## Requirements

- WordPress 6.5+
- PHP 8.1+
- cURL enabled
- Groq API key (free tier available)
- Pexels API key (free tier available)

## Installation

Option 1: WordPress Plugin Repository (Recommended)
1. In your WordPress Dashboard, go to Plugins → Add New
2. Search for "AI Blogger"
3. Click "Install Now" and then "Activate"
4. Navigate to Settings → AI Blogger to configure your API key

Option 2: Manual Installation
1. Download the plugin ZIP file
2. Navigate to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Activate the plugin
5. Navigate to Settings → AI Blogger to configure your API key

The plugin is available on the [WordPress Plugin Repository](https://wordpress.org/plugins/ai-blogger/)

## Configuration

1. Get your Groq API key from [Groq Cloud Console](https://console.groq.com)
2. Get your Pexels API key from [Pexels API](https://www.pexels.com/api/)
3. In WordPress admin:
   - Go to Settings → AI Blogger
   - Enter your API keys
   - Select default model and content parameters
   - Save changes

## External Services

### Groq API
- **Service Name:** Groq API
- **Data Sent:** Blog title, user prompt
- **Data Received:** AI-generated blog post
- **Privacy Policy:** [Groq Privacy Policy](https://console.groq.com/terms)
- **Terms of Service:** [Groq Terms of Use](https://console.groq.com/terms)

### Pexels API
- **Service Name:** Pexels API
- **Data Sent:** Search keywords based on blog content
- **Data Received:** Relevant images for blog posts
- **Privacy Policy:** [Pexels Privacy Policy](https://www.pexels.com/privacy-policy/)
- **Terms of Service:** [Pexels Terms of Service](https://www.pexels.com/terms-of-service/)

## Available LLM Models

AI Blogger supports these state-of-the-art models:

| Model Name          | Context Window | Best For                  |
|---------------------|----------------|---------------------------|
| Mixtral-8x7b-32768  | 32k tokens     | Long-form content         |
| Llama2-70b-4096     | 4k tokens      | General purpose writing   |
| llama-3.3-70b-versatile      | 128k tokens      | best for writing         |
| Gemma-7b            | 8k tokens      | Quick generation          |

## Usage

1. Create New Post:
   - Navigate to Posts → Add New
   - Click "Generate with AI" button
   - Enter topic/keywords
   - Select content parameters
   - Generate and refine content

2. Bulk Generation:
   - Navigate to Tools → AI Blogger
   - Upload CSV with topics/parameters
   - Queue multiple posts for generation

## Links

- [View Details & Documentation](https://github.com/HridoyVaraby/Groq-Blogger)

## Changelog

### 1.0.6 - 2025-09-05

- **Feature:** Added support for all models from the Groq API.
- **Tweak:** The model selection dropdown in the settings now dynamically fetches the latest models from Groq.

### 1.0.3 - 2025-02-09

- Added Settings link next to Deactivate button in plugins list
- Improved settings page accessibility

### 1.0.2 - 2025-02-08

- Chaged the plugin name to AI Blogger
- Improved the plugin

### 1.0.0 - Initial Release (February 2025)

- Initial plugin release
- Basic content generation functionality
- Support for 4 LLM models
- API integration with Groq Cloud
- Admin settings panel
- Content template system

### 0.9.0 - Beta Release (January 2025)

- Beta testing phase
- Core functionality implemented
- Basic error handling
- Localization support added

### 0.5.0 - Alpha Release (December 2024)

- Initial development version
- API connection prototype
- Admin interface skeleton
- Content generation proof-of-concept
