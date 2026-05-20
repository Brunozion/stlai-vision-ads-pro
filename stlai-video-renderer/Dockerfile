FROM node:20-bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package*.json ./
RUN npm ci --omit=dev

COPY server.js README.md ./

RUN mkdir -p temp renders

ENV PORT=3000

EXPOSE 3000

CMD ["npm", "start"]
