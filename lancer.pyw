import subprocess
import webbrowser
import time
import os
import sys
import urllib.request

DOSSIER  = os.path.dirname(os.path.abspath(__file__))
SERVEUR  = os.path.join(DOSSIER, "serveur_flask_1.py")
HTML     = os.path.join(DOSSIER, "interface_alois.html")
URL_PING = "http://127.0.0.1:5000/ping"

# Démarrer Flask en arrière-plan, sans fenêtre console
proc = subprocess.Popen(
    [sys.executable, SERVEUR],
    cwd=DOSSIER,
    creationflags=subprocess.CREATE_NO_WINDOW,
)

# Attendre que le serveur soit prêt (max 15 secondes)
for _ in range(30):
    try:
        urllib.request.urlopen(URL_PING, timeout=1)
        break
    except Exception:
        time.sleep(0.5)

# Ouvrir l'interface dans le navigateur par défaut
webbrowser.open(HTML)
