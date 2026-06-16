FROM node:22-alpine
WORKDIR /app
COPY mcp-server/package.json ./
RUN npm install --omit=dev
COPY mcp-server/index.js ./
CMD ["node", "index.js"]
