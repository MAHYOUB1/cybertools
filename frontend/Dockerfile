# ================================================================
# Frontend Dockerfile - Nginx
# ================================================================
FROM nginx:alpine

# نسخ إعدادات Nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf

# نسخ ملفات HTML والأصول
COPY pages/ /usr/share/nginx/html/
COPY assets/ /usr/share/nginx/html/assets/

EXPOSE 80
