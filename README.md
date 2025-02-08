# Groq Blogger - WordPress Plugin

![Plugin Version](https://img.shields.io/badge/Version-1.0.0-blue) 
![WordPress Tested](https://img.shields.io/badge/WordPress-6.4+-green)

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

## Requirements

- WordPress 6.4+
- PHP 7.4+
- cURL enabled
- Groq API key (free tier available)

## Installation

1. Download the plugin ZIP file
2. Navigate to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Activate the plugin
5. Navigate to Settings → Groq Blogger to configure your API key

## Configuration

1. Get your Groq API key from [Groq Cloud Console](https://console.groq.com)
2. In WordPress admin:
   - Go to Settings → Groq Blogger
   - Enter your API key
   - Select default model and content parameters
   - Save changes

## Available LLM Models

Groq Blogger supports these state-of-the-art models:

| Model Name          | Context Window | Best For                  |
|---------------------|----------------|---------------------------|
| Mixtral-8x7b-32768  | 32k tokens     | Long-form content         |
| Llama2-70b-4096     | 4k tokens      | General purpose writing   |
| CodeLlama-34b       | 4k tokens      | Technical content         |
| Gemma-7b            | 8k tokens      | Quick generation          |

## Usage

1. Create New Post:
   - Navigate to Posts → Add New
   - Click "Generate with Groq" button
   - Enter topic/keywords
   - Select content parameters
   - Generate and refine content

2. Bulk Generation:
   - Navigate to Tools → Groq Blogger
   - Upload CSV with topics/parameters
   - Queue multiple posts for generation

## Changelog

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
