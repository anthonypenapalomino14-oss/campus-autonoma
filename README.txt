DEPLOY EN RENDER

1. Descomprime este ZIP.
2. Sube los archivos a un repositorio de GitHub.
3. En Render, elige New + > Web Service y conecta el repositorio.
4. Render detectará render.yaml. Si debes ingresarlo manualmente:
   Build Command: pip install -r requirements.txt
   Start Command: gunicorn app:app
5. Elige una instancia gratuita si está disponible y pulsa Deploy.

La app.py sirve el index.html existente. La interfaz y sus funciones JavaScript
siguen en el navegador; los campos se guardan en localStorage del navegador,
no en una base de datos compartida del servidor.
