# ==============================================================================
#  PROJET ALOIS — Détection précoce d'Alzheimer par analyse vocale
#  Script principal : Extraction des biomarqueurs + Décision IA via API Groq
#
#  Auteurs : Équipe ECE ING2 Groupe 9
#
#  COMMENT ÇA MARCHE (résumé simple) :
#  1. On charge un fichier audio (.wav)
#  2. On extrait 4 biomarqueurs vocaux avec Python (librosa, nltk...)
#  3. On envoie ces biomarqueurs à l'API Groq (IA — 100% gratuite)
#  4. L'IA (modèle Llama 3.3 70B) retourne un score + une recommandation médicale
#  5. On affiche tout proprement dans la console
#
#  LIBRAIRIES À INSTALLER (une seule fois, dans votre terminal) :
#  pip install librosa speechrecognition nltk requests
#
#  CLÉ API MISTRAL GRATUITE (sans carte bancaire) :
#  1. Créez un compte sur https://console.mistral.ai
#  2. Vérifiez votre numéro de téléphone (requis une seule fois)
#  3. Allez dans "API Keys" et créez une clé
#  4. Copiez-la dans CLE_API_MISTRAL ci-dessous
# ==============================================================================


# --- IMPORTATION DES LIBRAIRIES ---
# (ce sont des "boîtes à outils" que Python va utiliser)

import os           # pour vérifier si un fichier existe
import json         # pour lire/écrire des données au format JSON
import warnings     # pour masquer des messages d'erreur non critiques

import librosa      # pour analyser le signal audio (tonalité, pauses...)
import numpy as np  # pour faire des calculs mathématiques (moyenne, écart-type...)

import speech_recognition as sr  # pour transcrire la voix en texte
import nltk                       # pour analyser le texte (compter les mots...)
from nltk.tokenize import word_tokenize

import requests     # pour appeler l'API Groq (envoyer une requête internet)

# On masque les avertissements non critiques de librosa pour garder la console propre
warnings.filterwarnings('ignore')


# ==============================================================================
#  CONFIGURATION — À MODIFIER SELON VOS BESOINS
# ==============================================================================

# Votre clé API Mistral (gratuite, sans carte bancaire)
# Créez votre compte et récupérez votre clé sur : https://console.mistral.ai
CLE_API_MISTRAL = "LfXU0Nek0y11SKtYqY4JWdH4kM95R9Vi"

# Le modèle Mistral à utiliser (plan gratuit "Experiment")
# "mistral-small-latest" = rapide, capable, disponible sur le tier gratuit
MODELE_MISTRAL = "mistral-small-latest"

# Le chemin vers votre fichier audio à analyser
# Remplacez par l'adresse de votre propre fichier .wav
CHEMIN_FICHIER_AUDIO = r"C:\Users\matto\Desktop\projet alois\2.wav"


# ==============================================================================
#  ÉTAPE 0 — PRÉPARATION DE NLTK
#  (téléchargement des données nécessaires pour analyser le texte en français)
# ==============================================================================

print("Vérification des données NLTK...")
try:
    nltk.data.find('tokenizers/punkt_tab')
except LookupError:
    print("  -> Téléchargement des données NLTK (une seule fois)...")
    nltk.download('punkt', quiet=True)
    nltk.download('punkt_tab', quiet=True)
print("  -> OK\n")


# ==============================================================================
#  ÉTAPE 1 — EXTRACTION DES BIOMARQUEURS VOCAUX
#  Cette fonction prend un fichier audio et retourne 4 mesures chiffrées
# ==============================================================================

def extraire_biomarqueurs(chemin_audio):
    """
    Analyse un fichier audio et retourne un dictionnaire avec 4 biomarqueurs.

    Un biomarqueur vocal = une mesure chiffrée extraite de la voix
    qui peut indiquer un trouble cognitif précoce.
    """

    print("=" * 55)
    print("  ÉTAPE 1 — EXTRACTION DES BIOMARQUEURS VOCAUX")
    print("=" * 55)

    # --- Chargement du fichier audio ---
    # librosa.load() lit le fichier .wav et le convertit en tableau de nombres
    # y = le signal audio (liste de valeurs sonores)
    # sr_rate = la fréquence d'échantillonnage (ex: 44100 Hz = qualité CD)
    print(f"\nChargement du fichier : {os.path.basename(chemin_audio)}")
    try:
        y, sr_rate = librosa.load(chemin_audio, sr=None)
        duree_totale = librosa.get_duration(y=y, sr=sr_rate)
        print(f"  -> Durée de l'audio : {round(duree_totale, 1)} secondes")
        print(f"  -> Fréquence d'échantillonnage : {sr_rate} Hz")
    except Exception as erreur:
        print(f"  -> ERREUR : impossible de lire le fichier audio.")
        print(f"     Détail : {erreur}")
        return None  # On arrête la fonction si le fichier ne peut pas être lu


    # ------------------------------------------------------------------
    # BIOMARQUEUR 1 : LA TONALITÉ (variation du pitch / F0)
    #
    # Pourquoi c'est intéressant ?
    # Les personnes atteintes d'Alzheimer ont souvent une voix plus monotone,
    # avec moins de variation dans la hauteur de leur voix (pitch).
    # Une faible variation de tonalité peut donc être un signal d'alerte.
    #
    # Comment on mesure ?
    # librosa.pyin() extrait la fréquence fondamentale (F0) à chaque instant.
    # On calcule ensuite l'écart-type de ces valeurs (= à quel point elles varient).
    # ------------------------------------------------------------------
    print("\n[1/4] Extraction de la TONALITÉ (variation du pitch)...")

    f0, _, _ = librosa.pyin(
        y,
        fmin=librosa.note_to_hz('C2'),  # fréquence minimum (voix grave)
        fmax=librosa.note_to_hz('C7')   # fréquence maximum (voix aiguë)
    )

    # f0 contient des NaN (= "pas de valeur") pour les silences → on les retire
    f0_valides = f0[~np.isnan(f0)]

    # L'écart-type (np.std) mesure à quel point les valeurs s'écartent de la moyenne
    # → Plus l'écart-type est élevé, plus la voix est expressive/variée
    if len(f0_valides) > 0:
        variation_tonalite = round(float(np.std(f0_valides)), 2)
    else:
        variation_tonalite = 0.0  # si aucune voix détectée

    print(f"  -> Variation de tonalité : {variation_tonalite} Hz")
    print(f"     (référence saine : > 30 Hz | alerte si < 15 Hz)")


    # ------------------------------------------------------------------
    # BIOMARQUEUR 2 : LES PAUSES (ratio silence / temps total)
    #
    # Pourquoi c'est intéressant ?
    # Les personnes Alzheimer font plus de pauses, cherchent leurs mots,
    # et parlent de manière moins fluide. Un ratio de silence élevé
    # peut indiquer des difficultés à trouver les mots.
    #
    # Comment on mesure ?
    # librosa.effects.split() détecte les segments où quelqu'un parle.
    # On calcule la durée totale de parole, puis on fait :
    # ratio_silence = (durée_totale - durée_parole) / durée_totale
    # ------------------------------------------------------------------
    print("\n[2/4] Calcul du RATIO DE SILENCE (pauses)...")

    # On récupère tous les intervalles [début, fin] où il y a de la parole
    # top_db=20 signifie : on considère comme silence tout ce qui est
    # 20 décibels en dessous du maximum
    intervalles_parole = librosa.effects.split(y, top_db=20)

    # On additionne la durée de chaque segment de parole
    duree_parole_echantillons = sum([(fin - debut) for debut, fin in intervalles_parole])
    duree_parole_secondes = duree_parole_echantillons / sr_rate

    # Durée de silence = durée totale - durée de parole
    duree_silence = duree_totale - duree_parole_secondes

    # Le ratio va de 0 (pas de silence) à 1 (que du silence)
    ratio_silence = round(duree_silence / duree_totale, 2) if duree_totale > 0 else 0.0

    print(f"  -> Durée de parole : {round(duree_parole_secondes, 1)} s")
    print(f"  -> Durée de silence : {round(duree_silence, 1)} s")
    print(f"  -> Ratio de silence : {ratio_silence}")
    print(f"     (référence saine : < 0.35 | alerte si > 0.50)")


    # ------------------------------------------------------------------
    # BIOMARQUEUR 3 : LA RICHESSE LEXICALE (TTR - Type Token Ratio)
    #
    # Pourquoi c'est intéressant ?
    # Alzheimer entraîne une réduction du vocabulaire utilisé.
    # Les patients répètent souvent les mêmes mots et ont du mal à
    # trouver des mots variés.
    #
    # Comment on mesure ?
    # On transcrit la voix en texte (Google Speech Recognition).
    # TTR = nombre de mots UNIQUES / nombre total de mots
    # Exemple : "le chat mange le poisson" → 5 mots, 4 uniques → TTR = 0.8
    # ------------------------------------------------------------------
    print("\n[3/4] Transcription et calcul de la RICHESSE LEXICALE...")

    recognizer = sr.Recognizer()
    texte_transcrit = ""
    richesse_lexicale = 0.0
    nombre_mots = 0
    debit_mots_par_minute = 0.0

    try:
        with sr.AudioFile(chemin_audio) as source:
            audio_data = recognizer.record(source)

        # Transcription via le service Google (nécessite internet)
        texte_transcrit = recognizer.recognize_google(audio_data, language="fr-FR")
        print(f"  -> Texte transcrit : \"{texte_transcrit}\"")

        # On découpe le texte en mots avec NLTK
        tous_les_mots = word_tokenize(texte_transcrit.lower(), language='french')

        # On garde uniquement les vrais mots (on enlève la ponctuation, les chiffres...)
        mots_purs = [mot for mot in tous_les_mots if mot.isalpha()]
        nombre_mots = len(mots_purs)

        # Calcul du TTR : mots uniques / total des mots
        if nombre_mots > 0:
            mots_uniques = set(mots_purs)  # set() = liste sans doublons
            richesse_lexicale = round(len(mots_uniques) / nombre_mots, 2)

        print(f"  -> Nombre de mots : {nombre_mots}")
        print(f"  -> Richesse lexicale (TTR) : {richesse_lexicale}")
        print(f"     (référence saine : > 0.6 | alerte si < 0.4)")

    except sr.UnknownValueError:
        texte_transcrit = "[Audio incompréhensible - trop de bruit ou trop court]"
        print(f"  -> ATTENTION : {texte_transcrit}")
    except sr.RequestError as e:
        texte_transcrit = f"[Erreur de connexion au service Google : {e}]"
        print(f"  -> ATTENTION : {texte_transcrit}")


    # ------------------------------------------------------------------
    # BIOMARQUEUR 4 : LE DÉBIT DE PAROLE (mots par minute)
    #
    # Pourquoi c'est intéressant ?
    # Un débit de parole lent peut indiquer des difficultés cognitives.
    # Les personnes Alzheimer parlent souvent plus lentement.
    #
    # Comment on mesure ?
    # On divise le nombre de mots transcrits par la durée de parole.
    # ------------------------------------------------------------------
    print("\n[4/4] Calcul du DÉBIT DE PAROLE...")

    if duree_parole_secondes > 0 and nombre_mots > 0:
        # Conversion en mots par minute : (mots / secondes) × 60
        debit_mots_par_minute = round((nombre_mots / duree_parole_secondes) * 60, 1)
    else:
        debit_mots_par_minute = 0.0

    print(f"  -> Débit de parole : {debit_mots_par_minute} mots/minute")
    print(f"     (référence saine : 120-180 mots/min | alerte si < 80 mots/min)")


    # --- On regroupe tous les résultats dans un dictionnaire ---
    biomarqueurs = {
        "duree_audio_secondes":    round(duree_totale, 1),
        "texte_transcrit":         texte_transcrit,
        "variation_tonalite_hz":   variation_tonalite,
        "ratio_silence":           ratio_silence,
        "richesse_lexicale_ttr":   richesse_lexicale,
        "debit_mots_par_minute":   debit_mots_par_minute
    }

    print("\n  -> Extraction des biomarqueurs terminée ✅")
    return biomarqueurs


# ==============================================================================
#  ÉTAPE 2 — APPEL À L'API MISTRAL (IA — plan gratuit disponible)
#  On envoie les biomarqueurs à Mistral et on reçoit une analyse médicale
#
#  Mistral utilise le même format d'API qu'OpenAI (format "chat completions"),
#  ce qui le rend très simple à intégrer. Avantage supplémentaire : Mistral
#  est une entreprise française (RGPD, hébergement européen).
# ==============================================================================

def appeler_api_mistral(biomarqueurs, cle_api, modele=MODELE_MISTRAL):
    """
    Envoie les biomarqueurs extraits à l'API Mistral AI.
    Le modèle va les interpréter et retourner un score de risque + une recommandation.

    C'est ici que l'intelligence artificielle intervient dans le projet.
    Mistral est une IA française, RGPD-compatible, avec un plan gratuit.
    """

    print("\n" + "=" * 55)
    print("  ÉTAPE 2 — ANALYSE PAR L'IA (API Mistral AI)")
    print("=" * 55)
    print(f"\nEnvoi des biomarqueurs à Mistral ({modele})...")

    # ------------------------------------------------------------------
    # LE PROMPT SYSTÈME — on définit le rôle du modèle IA
    # ------------------------------------------------------------------
    prompt_systeme = """Tu es un assistant médical d'aide à la décision, spécialisé dans 
la détection précoce de la maladie d'Alzheimer par analyse vocale.
Tu analyses des biomarqueurs vocaux et tu produis une évaluation structurée.
Tu réponds TOUJOURS et UNIQUEMENT avec un objet JSON valide, sans texte avant ni après,
sans balises markdown, sans ```json. Juste le JSON brut."""

    # ------------------------------------------------------------------
    # LE PROMPT UTILISATEUR — les données à analyser
    # ------------------------------------------------------------------
    prompt_utilisateur = f"""Voici les biomarqueurs vocaux extraits automatiquement d'un 
enregistrement de parole d'un patient :

--- BIOMARQUEURS MESURÉS ---
- Durée de l'enregistrement   : {biomarqueurs['duree_audio_secondes']} secondes
- Texte transcrit             : "{biomarqueurs['texte_transcrit']}"
- Variation de tonalité (F0)  : {biomarqueurs['variation_tonalite_hz']} Hz
  (référence saine : > 30 Hz = normal | < 15 Hz = signal d'alerte)
- Ratio de silence            : {biomarqueurs['ratio_silence']}
  (référence saine : < 0.35 = normal | > 0.50 = signal d'alerte)
- Richesse lexicale (TTR)     : {biomarqueurs['richesse_lexicale_ttr']}
  (référence saine : > 0.6 = normal | < 0.4 = signal d'alerte)
- Débit de parole             : {biomarqueurs['debit_mots_par_minute']} mots/minute
  (référence saine : 120-180 = normal | < 80 = signal d'alerte)

Sur la base de ces indicateurs et de la littérature médicale sur les biomarqueurs vocaux 
de la maladie d'Alzheimer, génère une analyse structurée.

IMPORTANT :
- Ce score n'est PAS un diagnostic médical, c'est un outil d'aide à la décision.
- Reste prudent et nuancé dans ton analyse.
- Si l'enregistrement est trop court ou incompréhensible, indique-le clairement.

Réponds avec exactement ce JSON (et rien d'autre) :
{{
  "score_risque": <entier de 0 à 100>,
  "niveau_alerte": "<vert ou orange ou rouge>",
  "analyse_biomarqueurs": "<explication en 2-3 phrases des indicateurs>",
  "recommandation_medecin": "<action concrète conseillée au médecin généraliste>",
  "indicateurs_preoccupants": ["<biomarqueur anormal 1>", "<biomarqueur anormal 2>"],
  "fiabilite_analyse": "<haute, moyenne ou faible>"
}}"""

    # ------------------------------------------------------------------
    # L'APPEL HTTP À L'API MISTRAL
    # Mistral utilise le même format que l'API OpenAI ("chat completions")
    # Seuls l'URL et la clé changent par rapport à Groq
    # ------------------------------------------------------------------

    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {cle_api}"   # même format "Bearer" que Groq/OpenAI
    }

    body = {
        "model": modele,                        # "mistral-small-latest"
        "max_tokens": 1000,
        "temperature": 0.3,                     # 0.3 = réponse fiable et stable
        "messages": [
            {
                "role": "system",               # instructions de comportement
                "content": prompt_systeme
            },
            {
                "role": "user",                 # notre question avec les données
                "content": prompt_utilisateur
            }
        ]
    }

    texte_reponse = ""

    try:
        # URL de l'API Mistral — c'est la seule vraie différence avec Groq
        reponse = requests.post(
            "https://api.mistral.ai/v1/chat/completions",
            headers=headers,
            json=body,
            timeout=30
        )

        reponse.raise_for_status()

        donnees = reponse.json()

        # Format de réponse identique à OpenAI/Groq
        texte_reponse = donnees["choices"][0]["message"]["content"].strip()

        print("  -> Réponse reçue de Mistral ✅")
        print(f"  -> Tokens utilisés : {donnees.get('usage', {}).get('total_tokens', '?')}")

        # Nettoyage au cas où le modèle aurait ajouté des backticks markdown
        if texte_reponse.startswith("```"):
            texte_reponse = texte_reponse.split("```")[1]
            if texte_reponse.startswith("json"):
                texte_reponse = texte_reponse[4:]
            texte_reponse = texte_reponse.strip()

        analyse_ia = json.loads(texte_reponse)
        return analyse_ia

    except requests.exceptions.ConnectionError:
        print("  -> ERREUR : Pas de connexion internet. Vérifiez votre réseau.")
        return None
    except requests.exceptions.Timeout:
        print("  -> ERREUR : L'API Mistral met trop de temps à répondre (timeout 30s).")
        return None
    except requests.exceptions.HTTPError as e:
        print(f"  -> ERREUR API HTTP {reponse.status_code} : {e}")
        if reponse.status_code == 401:
            print("     → Clé API invalide. Vérifiez CLE_API_MISTRAL dans ce fichier.")
        elif reponse.status_code == 429:
            print("     → Limite du plan gratuit atteinte. Réessayez dans quelques secondes.")
        return None
    except json.JSONDecodeError:
        print("  -> ERREUR : Mistral n'a pas répondu en JSON valide.")
        print(f"     Réponse brute reçue : {texte_reponse[:200]}...")
        return None
    except Exception as e:
        print(f"  -> ERREUR inattendue : {e}")
        return None


# ==============================================================================
#  ÉTAPE 3 — AFFICHAGE DES RÉSULTATS
#  On affiche tout de manière lisible dans la console
# ==============================================================================

def afficher_resultats(biomarqueurs, analyse_ia):
    """
    Affiche un rapport complet et lisible dans la console.
    """

    print("\n")
    print("█" * 55)
    print("█  RAPPORT FINAL — PROJET ALOIS                      █")
    print("█  Détection précoce Alzheimer par analyse vocale     █")
    print("█" * 55)

    # --- Section 1 : Biomarqueurs extraits ---
    print("\n📊 BIOMARQUEURS VOCAUX MESURÉS :")
    print("-" * 55)
    print(f"  Durée de l'enregistrement  : {biomarqueurs['duree_audio_secondes']} secondes")
    print(f"  Texte transcrit            : \"{biomarqueurs['texte_transcrit']}\"")
    print(f"  🎤 Variation tonalité (F0) : {biomarqueurs['variation_tonalite_hz']} Hz")
    print(f"  ⏱️  Ratio de silence        : {biomarqueurs['ratio_silence']}")
    print(f"  📚 Richesse lexicale (TTR) : {biomarqueurs['richesse_lexicale_ttr']}")
    print(f"  🗣️  Débit de parole         : {biomarqueurs['debit_mots_par_minute']} mots/min")

    # --- Section 2 : Analyse IA ---
    if analyse_ia is None:
        print("\n⚠️  L'analyse IA n'a pas pu être effectuée (voir erreurs ci-dessus).")
        print("    Les biomarqueurs bruts sont disponibles pour une interprétation manuelle.")
        return

    print("\n🤖 ANALYSE INTELLIGENCE ARTIFICIELLE (Mistral AI) :")
    print("-" * 55)

    # On détermine l'emoji de couleur selon le niveau d'alerte
    niveau = analyse_ia.get("niveau_alerte", "inconnu").lower()
    if niveau == "vert":
        couleur = "🟢 VERT — Faible risque"
    elif niveau == "orange":
        couleur = "🟠 ORANGE — Risque modéré"
    elif niveau == "rouge":
        couleur = "🔴 ROUGE — Risque élevé"
    else:
        couleur = f"⚪ {niveau}"

    score = analyse_ia.get("score_risque", "N/A")
    fiabilite = analyse_ia.get("fiabilite_analyse", "N/A")

    print(f"  Niveau d'alerte    : {couleur}")
    print(f"  Score de risque    : {score} / 100")
    print(f"  Fiabilité analyse  : {fiabilite}")

    # Indicateurs préoccupants
    indicateurs = analyse_ia.get("indicateurs_preoccupants", [])
    if indicateurs:
        print(f"\n  ⚠️  Indicateurs préoccupants :")
        for indicateur in indicateurs:
            print(f"      - {indicateur}")
    else:
        print(f"\n  ✅ Aucun indicateur préoccupant détecté.")

    # Analyse détaillée
    analyse = analyse_ia.get("analyse_biomarqueurs", "Analyse non disponible.")
    print(f"\n  📋 Analyse détaillée :")
    print(f"     {analyse}")

    # Recommandation
    recommandation = analyse_ia.get("recommandation_medecin", "Recommandation non disponible.")
    print(f"\n  💊 Recommandation pour le médecin :")
    print(f"     {recommandation}")

    # Avertissement médical obligatoire
    print("\n" + "-" * 55)
    print("  ⚠️  AVERTISSEMENT MÉDICAL IMPORTANT :")
    print("  Ce score est un outil d'aide à la décision, PAS un diagnostic.")
    print("  Seul un médecin spécialiste peut établir un diagnostic d'Alzheimer.")
    print("-" * 55)

    # On sauvegarde aussi les résultats dans un fichier JSON
    resultats_complets = {
        "biomarqueurs": biomarqueurs,
        "analyse_ia": analyse_ia
    }

    fichier_sortie = "resultats_alois.json"
    with open(fichier_sortie, "w", encoding="utf-8") as f:
        json.dump(resultats_complets, f, ensure_ascii=False, indent=2)
    print(f"\n  💾 Résultats sauvegardés dans : {fichier_sortie}")
    print("█" * 55 + "\n")


# ==============================================================================
#  PROGRAMME PRINCIPAL — C'est ici que tout se lance
# ==============================================================================

if __name__ == "__main__":

    print("\n")
    print("=" * 55)
    print("  PROJET ALOIS — Analyse vocale Alzheimer (POC)")
    print("  École ECE Paris — ING2 Groupe 9")
    print("=" * 55)

    # --- Vérification de la clé API Mistral ---
    if CLE_API_MISTRAL == "METTEZ_VOTRE_CLE_MISTRAL_ICI":
        print("\n⚠️  ATTENTION : Vous n'avez pas renseigné votre clé API Mistral !")
        print("   1. Créez un compte GRATUIT sur : https://console.mistral.ai")
        print("   2. Vérifiez votre numéro de téléphone (requis une seule fois)")
        print("   3. Allez dans 'API Keys' et créez une nouvelle clé")
        print("   4. Copiez-la dans ce fichier à la ligne : CLE_API_MISTRAL = '...'")
        print("\n   Le programme va quand même extraire les biomarqueurs,")
        print("   mais l'analyse IA (Mistral) ne sera pas effectuée.\n")

    # --- Vérification que le fichier audio existe ---
    print(f"\nRecherche du fichier audio :")
    print(f"  -> {CHEMIN_FICHIER_AUDIO}")

    if not os.path.exists(CHEMIN_FICHIER_AUDIO):
        print("\n❌ ERREUR : Fichier audio introuvable !")
        print("   Vérifiez le chemin dans la variable CHEMIN_FICHIER_AUDIO")
        print("   (en haut de ce script, dans la section CONFIGURATION)")
    else:
        print("  -> Fichier trouvé ✅\n")

        # --- ÉTAPE 1 : On extrait les biomarqueurs ---
        biomarqueurs = extraire_biomarqueurs(CHEMIN_FICHIER_AUDIO)

        if biomarqueurs is None:
            print("\n❌ L'extraction des biomarqueurs a échoué. Arrêt du programme.")
        else:

            # --- ÉTAPE 2 : On appelle l'API Mistral pour l'analyse IA ---
            if CLE_API_MISTRAL != "METTEZ_VOTRE_CLE_MISTRAL_ICI":
                analyse_ia = appeler_api_mistral(biomarqueurs, CLE_API_MISTRAL)
            else:
                analyse_ia = None  # Pas d'analyse IA si pas de clé

            # --- ÉTAPE 3 : On affiche les résultats ---
            afficher_resultats(biomarqueurs, analyse_ia)
