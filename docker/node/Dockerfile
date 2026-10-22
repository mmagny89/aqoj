# syntax=docker/dockerfile:1
# Serveur de developpement uniquement — le build de production est une
# etape du Dockerfile PHP (frontend_builder), pas de ce fichier.
ARG NODE_VERSION
FROM node:${NODE_VERSION}
WORKDIR /frontend
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
