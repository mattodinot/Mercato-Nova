# ==============================================================================
#  PROJET ALOIS — Serveur Flask
#  Ce fichier fait le lien entre l'interface web (HTML) et le script d'analyse.
#
#  COMMENT ÇA MARCHE :
#  1. L'interface HTML envoie le fichier audio à ce serveur via HTTP
#  2. Ce serveur appelle les fonctions du script d'analyse Python
#  3. Il renvoie les résultats en JSON à l'interface
#
#  LANCEMENT :
#  Dans votre terminal :  python serveur_flask.py
#  Le serveur démarre sur : http://127.0.0.1:5000
#
#  INSTALLATION :
#  pip install flask flask-cors
# ==============================================================================

import os
import tempfile
from flask import Flask, request, jsonify
from flask_cors import CORS   # permet à la page HTML d'appeler ce serveur

# On importe les fonctions de notre script d'analyse principal
# (les deux fichiers doivent être dans le même dossier)
from alois_analyse_vocale_1 import extraire_biomarqueurs, appeler_api_mistral, CLE_API_MISTRAL


# --- Création de l'application Flask ---
app = Flask(__name__)

# CORS = Cross-Origin Resource Sharing
# Sans ça, le navigateur bloque les appels depuis le fichier HTML vers ce serveur
CORS(app)


# ==============================================================================
#  ROUTE PRINCIPALE : POST /analyser
#  L'interface envoie le fichier audio ici, on renvoie les résultats
# ==============================================================================

@app.route("/analyser", methods=["POST"])
def analyser():
    """
    Reçoit un fichier audio, l'analyse, et renvoie les résultats en JSON.
    """

    # --- Vérification qu'un fichier a bien été envoyé ---
    if "audio" not in request.files:
        return jsonify({"erreur": "Aucun fichier audio reçu."}), 400

    fichier = request.files["audio"]

    if fichier.filename == "":
        return jsonify({"erreur": "Le fichier reçu est vide."}), 400

    # --- Sauvegarde temporaire du fichier ---
    # On ne peut pas analyser un fichier directement depuis la mémoire avec librosa,
    # donc on le sauvegarde temporairement sur le disque
    suffixe = os.path.splitext(fichier.filename)[1]  # ex: ".wav"

    # tempfile.NamedTemporaryFile crée un fichier temporaire qui se supprime tout seul
    with tempfile.NamedTemporaryFile(delete=False, suffix=suffixe) as tmp:
        chemin_tmp = tmp.name
        fichier.save(chemin_tmp)

    try:
        # --- ÉTAPE 1 : Extraction des biomarqueurs ---
        print(f"\n[SERVEUR] Fichier reçu : {fichier.filename}")
        print(f"[SERVEUR] Sauvegardé temporairement : {chemin_tmp}")

        biomarqueurs = extraire_biomarqueurs(chemin_tmp)

        if biomarqueurs is None:
            return jsonify({"erreur": "Impossible d'analyser ce fichier audio. Vérifiez le format."}), 500

        # --- ÉTAPE 2 : Analyse IA via API Mistral (plan gratuit) ---
        analyse_ia = None

        if CLE_API_MISTRAL != "METTEZ_VOTRE_CLE_MISTRAL_ICI":
            analyse_ia = appeler_api_mistral(biomarqueurs, CLE_API_MISTRAL)
        else:
            print("[SERVEUR] Clé API Mistral non configurée — analyse IA ignorée.")

        # --- Retour des résultats en JSON ---
        return jsonify({
            "biomarqueurs": biomarqueurs,
            "analyse_ia":   analyse_ia
        })

    except Exception as erreur:
        print(f"[SERVEUR] ERREUR : {erreur}")
        return jsonify({"erreur": str(erreur)}), 500

    finally:
        # On supprime le fichier temporaire dans tous les cas
        if os.path.exists(chemin_tmp):
            os.remove(chemin_tmp)
            print(f"[SERVEUR] Fichier temporaire supprimé.")


# ==============================================================================
#  ROUTE DE TEST : GET /ping
#  Permet de vérifier rapidement que le serveur est bien lancé
# ==============================================================================

@app.route("/ping", methods=["GET"])
def ping():
    return jsonify({"statut": "ok", "message": "Serveur Alois opérationnel ✅"})


# ==============================================================================
#  LANCEMENT DU SERVEUR
# ==============================================================================

if __name__ == "__main__":
    print("\n" + "=" * 50)
    print("  PROJET ALOIS — Serveur Flask")
    print("=" * 50)
    print("  Serveur démarré sur : http://127.0.0.1:5000")
    print("  Ouvrez alois_interface.html dans votre navigateur")
    print("  Appuyez sur Ctrl+C pour arrêter le serveur")
    print("=" * 50 + "\n")

    # debug=True = affiche les erreurs détaillées dans la console (utile en dev)
    app.run(debug=True, host="127.0.0.1", port=5000)
