FROM node:20-alpine

COPY docker/node/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app

EXPOSE 5173

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
