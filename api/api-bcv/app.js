import * as cheerio from 'cheerio';
import { Agent, setGlobalDispatcher } from 'undici';
import express from 'express'; // Importación estándar para ES Modules

const app = express();
const PORT = process.env.PORT || 3000;

const agent = new Agent({
  connect: { rejectUnauthorized: false }
});
setGlobalDispatcher(agent);

async function obtenerTasaBCV() {
  const url = 'https://www.bcv.org.ve/';
  const response = await fetch(url, {
    headers: {
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    }
  });

  if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

  const html = await response.text();
  const $ = cheerio.load(html);

  return {
    usd: $('#dolar strong').text().trim(),
    eur: $('#euro strong').text().trim(),
    date: $('.pull-right.dinpro.center span').text().trim()
  };
}

// Endpoint de la API
app.get('/api-bcv', async (req, res) => {
  try {
    const datos = await obtenerTasaBCV();
    res.json({
      success: true,
      data: datos
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: error.message
    });
  }
});

app.listen(PORT, () => {
  console.log(`🚀 Servidor corriendo en http://localhost:${PORT}`);
});