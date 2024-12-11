# Installation

## Pré-requis

-   Un moteur de base de données (MySQL/MariaDB/PostgreSQL…) compatible avec Symfony.
https://dev.mysql.com/downloads/installer/
    
-   Composer (PHP)
https://getcomposer.org/download/

Idéalement, installer Tailwind et ses dépendances (hormis le bundle) pour pouvoir régénérer le CSS en cas de modifications sur le projet.

## Mise en place

Pour l’environnement, inclure les informations suivantes dans un .env(.local):

-   `DATABASE_URL`
    
-   `GROQ_API_KEY`
    
-   `SIGNATURE_KEY` (Un secret servant à la signature des liens d’invitation)
    

  

Similairement, à cause de certains soucis, `MERCURE_JWT_SECRET` a été mis dans le .env, et il faudrait idéalement le modifier, même s’il ne cause pas le même niveau de danger qu’un lien à la base de données et qu’une clé API dans un environnement de développement.

  

Pour les dépendances Composer, simplement écrire composer install.

  

Une fois la base de données mise, il faut préparer la base de données. Ecrire à la racine du projet:

`php bin/console doctrine:schema:create`
`php bin/console doctrine:migrations:migrate`

Ensuite, dans la base de données, manuellement créer un utilisateur d’ID 1, idéalement d’un mot de passe impossible à entrer. Celui-ci sera le compte utilisé par l’IA pour répondre.

# Présentation
## Problèmes UX

Pour envoyer un prompt personnalisé, il faut l’écrire dans le chat, puis cliquer sur le bouton dédié. Le message ne sera pas envoyé dans l’historique du chat, et sera directement envoyé dans le prompt de l’IA.

## À faire/finir

Compte tenu de certaines contraintes détails données très tardivement dans le projet, il était difficile d’implémenter ou terminer certaines fonctionnalités demandées à temps.

-   Mot de passe oublié
    
-   Créer un rôle dédié à l’IA, pour limiter les dégâts dans le cas d’un quelconque accès à son faux compte
    
-   Gestion des rôles
 
*Les détails donnés sont assez peu clairs, puisqu’il est dit que le modérateur doit créer des rooms, mais ensuite que les utilisateurs peuvent créer des rooms eux aussi. Il y a un manque de démarcation entre les utilisateurs et l'administration/modération*

-   Historique temporaire des conversations (dans des paramètres)
    
-   Sécuriser l’écoute de messages (par exemple, avec un token (JWT) prouvant que l’utilisateur ait accès à la room)
