# Audit legal IA, RGPD et cookies - Code4U

Date : 10 aout 2026

Ce document est un audit operationnel du site Code4U. Il ne remplace pas une validation par un avocat, mais liste les points de conformite traites dans le projet.

## Points corriges

- Transparence IA : les mentions legales, la politique de confidentialite, les CGV et l'interface chatbot signalent que l'utilisateur interagit avec une IA.
- Decision automatique : les pages legales precisent que le chatbot ne prend pas de decision juridique, contractuelle, financiere ou significative.
- Intervention humaine : les textes rappellent que l'utilisateur peut demander un suivi humain et qu'un devis/contrat doit etre valide par Code4U.
- Donnees sensibles : l'utilisateur est invite a ne pas saisir de mots de passe, donnees bancaires completes, secrets, donnees de sante ou autres donnees sensibles.
- Sous-traitance IA : la politique de confidentialite mentionne les prestataires techniques, la limitation des donnees transmises et les mecanismes de transfert hors UE si necessaires.
- Cookies : le bandeau propose maintenant "Tout accepter", "Tout refuser" et "Preferences" au meme niveau.
- Conservation : la politique mentionne des durees de conservation pour contact, chatbot/ticket, cookies analytiques et navigation.

## Points a verifier en production

- Lister le prestataire IA reel dans la politique de confidentialite si le backend utilise OpenAI, Anthropic ou un autre fournisseur.
- Verifier que les cookies analytiques ne sont pas poses avant consentement.
- Verifier que les logs serveur et tickets issus du chatbot respectent bien les durees annoncees.
- Tenir un registre RGPD minimal : finalites, bases legales, categories de donnees, sous-traitants, durees, mesures de securite.
- Si l'assistant genere du contenu publie automatiquement au nom de Code4U, ajouter un marquage clair du contenu genere ou assiste par IA.

## Sources officielles consultees

- CNIL, "IA : Informer les personnes concernees", 7 fevrier 2025.
- CNIL, "Les regles a suivre pour les cookies".
- CNIL, "Les durees de conservation des donnees", 2 avril 2026.
- Commission europeenne, lignes directrices sur les obligations de transparence IA, article 50 de l'AI Act, juillet 2026.
- Reglement (UE) 2024/1689 sur l'intelligence artificielle.
