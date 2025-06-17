# Jeet's Ultimate Super AI

A powerful AI assistant system built with PHP and OpenAI's GPT-4. This application provides a modern interface for chatting with an AI assistant and analyzing various types of files.

## Features

- Real-time chat interface with AI
- File upload and analysis (PDF, DOCX, TXT)
- Drag and drop file support
- Modern, responsive UI
- Multi-domain expertise (Programming, Business, Science, etc.)

## Prerequisites

- PHP 7.4 or higher
- XAMPP or similar PHP development environment
- OpenAI API key
- PHP extensions:
  - cURL
  - JSON
  - FileInfo

## Installation

1. Install XAMPP:
   - Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
   - Make sure Apache and PHP are installed

2. Clone or copy this project into your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\ai
   ```

3. Create the uploads directory:
   ```
   mkdir uploads
   chmod 777 uploads  # On Linux/Mac
   ```

4. Configure the application:
   - Open `config.php`
   - Replace `your_openai_api_key_here` with your actual OpenAI API key

## Usage

1. Start XAMPP:
   - Launch XAMPP Control Panel
   - Start Apache service

2. Open your browser and navigate to:
   ```
   http://localhost/ai
   ```

3. You can now:
   - Chat with the AI assistant
   - Upload files for analysis
   - Drag and drop files into the upload area

## File Analysis Support

The application supports the following file types:
- PDF (.pdf)
- Microsoft Word (.docx)
- Text files (.txt)

## Security Considerations

1. Make sure the uploads directory is not publicly accessible
2. Keep your OpenAI API key secure
3. Validate all user inputs
4. Set appropriate file permissions

## Troubleshooting

- **File Upload Issues**: Check that the 'uploads' directory exists and is writable
- **API Connection Problems**: Verify your OpenAI API key and internet connection
- **PHP Errors**: Check the PHP error log in XAMPP

## License

MIT License

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request 