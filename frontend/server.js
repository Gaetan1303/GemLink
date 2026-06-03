const express = require('express');
const path = require('path');

const app = express();
const PORT = 8080;

// Serve static files from the browser directory
app.use(express.static(path.join(__dirname, 'browser')));

// Fallback to index.html for all other routes (required for Angular SPA)
app.use((req, res) => {
  res.sendFile(path.join(__dirname, 'browser/index.html'));
});

app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});
