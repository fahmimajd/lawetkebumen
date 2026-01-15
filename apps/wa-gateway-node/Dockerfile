FROM node:20-alpine

RUN apk add --no-cache git

WORKDIR /usr/src/app

COPY package.json package-lock.json* ./
RUN npm install

COPY tsconfig.json ./
COPY src ./src

EXPOSE 9001

CMD ["npm", "run", "dev"]
