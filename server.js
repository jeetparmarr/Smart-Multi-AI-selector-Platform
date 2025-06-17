const express = require('express');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { OpenAI } = require('openai');
const pdfParse = require('pdf-parse');
const mammoth = require('mammoth');
const socketIO = require('socket.io');
const http = require('http');

require('dotenv').config();

const app = express();
const server = http.createServer(app);
const io = socketIO(server);

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static('public'));

// Configure multer for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    const uploadDir = 'uploads';
    if (!fs.existsSync(uploadDir)) {
      fs.mkdirSync(uploadDir);
    }
    cb(null, uploadDir);
  },
  filename: (req, file, cb) => {
    cb(null, Date.now() + path.extname(file.originalname));
  }
});

const upload = multer({ storage });

// Initialize OpenAI
const openai = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY
});

// File processing functions
async function processPDF(filePath) {
  const dataBuffer = fs.readFileSync(filePath);
  const data = await pdfParse(dataBuffer);
  return data.text;
}

async function processDocx(filePath) {
  const result = await mammoth.extractRawText({ path: filePath });
  return result.value;
}

// Routes
app.post('/api/chat', async (req, res) => {
  try {
    const { message, context } = req.body;
    
    const completion = await openai.chat.completions.create({
      model: "gpt-4",
      messages: [
        { role: "system", content: "You are Jeet's Ultimate Super AI — an ultra-intelligent, hyper-dynamic, highly responsive digital assistant." },
        ...context,
        { role: "user", content: message }
      ],
      temperature: 0.7,
      max_tokens: 1000
    });

    res.json({ response: completion.choices[0].message.content });
  } catch (error) {
    console.error('Error:', error);
    res.status(500).json({ error: 'Failed to process request' });
  }
});

app.post('/api/upload', upload.single('file'), async (req, res) => {
  try {
    const file = req.file;
    if (!file) {
      return res.status(400).json({ error: 'No file uploaded' });
    }

    let content;
    const filePath = path.join(__dirname, file.path);

    switch (path.extname(file.originalname).toLowerCase()) {
      case '.pdf':
        content = await processPDF(filePath);
        break;
      case '.docx':
        content = await processDocx(filePath);
        break;
      case '.txt':
        content = fs.readFileSync(filePath, 'utf8');
        break;
      default:
        return res.status(400).json({ error: 'Unsupported file type' });
    }

    // Process the content with OpenAI
    const completion = await openai.chat.completions.create({
      model: "gpt-4",
      messages: [
        { role: "system", content: "Analyze the following content and provide a detailed summary and insights:" },
        { role: "user", content: content }
      ],
      temperature: 0.7,
      max_tokens: 1000
    });

    // Clean up the uploaded file
    fs.unlinkSync(filePath);

    res.json({
      summary: completion.choices[0].message.content,
      originalContent: content
    });
  } catch (error) {
    console.error('Error:', error);
    res.status(500).json({ error: 'Failed to process file' });
  }
});

// Socket.IO connection handling
io.on('connection', (socket) => {
  console.log('Client connected');

  socket.on('chat message', async (msg) => {
    try {
      const completion = await openai.chat.completions.create({
        model: "gpt-4",
        messages: [
          { role: "system", content: "You are Jeet's Ultimate Super AI — an ultra-intelligent, hyper-dynamic, highly responsive digital assistant." },
          { role: "user", content: msg }
        ],
        temperature: 0.7,
        max_tokens: 1000
      });

      socket.emit('ai response', completion.choices[0].message.content);
    } catch (error) {
      console.error('Error:', error);
      socket.emit('error', 'Failed to process message');
    }
  });

  socket.on('disconnect', () => {
    console.log('Client disconnected');
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
}); 