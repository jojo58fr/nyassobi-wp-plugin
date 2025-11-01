# Nyassobi WP Plugin

Plugin WordPress leger qui centralise les reglages necessaires au front-end headless de Nyassobi et les expose a WPGraphQL.

## Installation

1. Copier le dossier `nyassobi-wp-plugin` dans `wp-content/plugins/`.
2. Dans l'admin WordPress, activer **Nyassobi WP Plugin**.

## Utilisation

Ouvrir **Reglages > Parametres Nyassobi** puis renseigner :

- Adresse email de contact.
- URL du formulaire d'inscription.
- URL de l'accord parental.
- URL des statuts associatifs.
- URL du reglement interieur.

Les valeurs sont accessibles en PHP via :

```php
$settings = Nyassobi_WP_Plugin::get_settings();
```

### WPGraphQL

Si le plugin [WPGraphQL](https://www.wpgraphql.com/) est actif, une requete `nyassobiSettings` devient disponible :

```graphql
query GetNyassobiSettings {
  nyassobiSettings {
    contactEmail
    signupFormUrl
    parentalAgreementUrl
    associationStatusUrl
    internalRulesUrl
  }
}
```

Toutes les cles retournees peuvent etre `null` si aucune valeur n'a encore ete renseignee.
