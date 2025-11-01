<?php
/**
 * Main plugin class for Nyassobi WP Plugin.
 *
 * @package NyassobiWPPlugin
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Nyassobi_WP_Plugin
{
    private const OPTION_NAME = 'nyassobi_wp_plugin';
    private const OPTION_GROUP = 'nyassobi_wp_plugin_group';
    private const PAGE_SLUG = 'nyassobi-wp-plugin';
    private const CAPABILITY = 'manage_options';

    /** @var self|null */
    private static $instance = null;

    /**
     * Singleton accessor.
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor hooks.
     */
    private function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('graphql_register_types', [$this, 'register_graphql_types']);
    }

    /**
     * Registers the option and its fields using the Settings API.
     */
    public function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [],
            ]
        );

        add_settings_section(
            'nyassobi_wp_plugin_section',
            __('Paramètres Nyassobi', 'nyassobi-wp-plugin'),
            function (): void {
                echo '<p>' . esc_html__(
                    'Indiquez les informations qui seront utilisées par le site headless Nyassobi.',
                    'nyassobi-wp-plugin'
                ) . '</p>';
            },
            self::PAGE_SLUG
        );

        foreach ($this->get_fields_definition() as $field_key => $field) {
            add_settings_field(
                $field_key,
                esc_html($field['label']),
                [$this, 'render_field'],
                self::PAGE_SLUG,
                'nyassobi_wp_plugin_section',
                [
                    'key' => $field_key,
                    'type' => $field['type'],
                    'description' => $field['description'],
                ]
            );
        }
    }

    /**
     * Adds the Nyassobi settings page to the admin menu.
     */
    public function register_settings_page(): void
    {
        add_options_page(
            __('Paramètres Nyassobi', 'nyassobi-wp-plugin'),
            __('Paramètres Nyassobi', 'nyassobi-wp-plugin'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Outputs the settings page markup.
     */
    public function render_settings_page(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Paramètres Nyassobi', 'nyassobi-wp-plugin'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders a field input.
     *
     * @param array<string,string> $args Context provided by add_settings_field.
     */
    public function render_field(array $args): void
    {
        $options = self::get_settings();
        $value = $options[$args['key']] ?? '';
        $description = $args['description'] ?? '';

        if ('email' === $args['type']) {
            printf(
                '<input type="email" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="regular-text" />',
                esc_attr(self::OPTION_NAME),
                esc_attr($args['key']),
                esc_attr($value)
            );
        } else {
            printf(
                '<input type="url" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="regular-text code" />',
                esc_attr(self::OPTION_NAME),
                esc_attr($args['key']),
                esc_attr($value)
            );
        }

        if ($description) {
            printf(
                '<p class="description">%s</p>',
                esc_html($description)
            );
        }
    }

    /**
     * Sanitizes option values before persisting them.
     *
     * @param array<string,mixed>|null $input Raw user input.
     *
     * @return array<string,string>
     */
    public function sanitize_settings(?array $input): array
    {
        $sanitized = [];
        $fields = $this->get_fields_definition();

        foreach ($fields as $key => $field) {
            $raw_value = isset($input[$key]) ? (string) $input[$key] : '';

            if ('email' === $field['type']) {
                $sanitized[$key] = sanitize_email($raw_value);
            } else {
                $sanitized[$key] = esc_url_raw($raw_value);
            }
        }

        return array_filter(
            $sanitized,
            static function (?string $value): bool {
                // Preserve empty strings to avoid PHP < 8.1 array_filter behavior differences.
                return null !== $value;
            }
        );
    }

    /**
     * Registers GraphQL type and field when WPGraphQL is active.
     */
    public function register_graphql_types(): void
    {
        if (! function_exists('register_graphql_object_type') || ! function_exists('register_graphql_field')) {
            return;
        }

        register_graphql_object_type(
            'NyassobiSettings',
            [
                'description' => __('Paramètres Nyassobi pour le site headless.', 'nyassobi-wp-plugin'),
                'fields' => [
                    'contactEmail' => [
                        'type' => 'String',
                        'description' => __('Adresse email de contact.', 'nyassobi-wp-plugin'),
                    ],
                    'signupFormUrl' => [
                        'type' => 'String',
                        'description' => __('URL du formulaire d\'inscription.', 'nyassobi-wp-plugin'),
                    ],
                    'parentalAgreementUrl' => [
                        'type' => 'String',
                        'description' => __('URL pour l\'accord parental.', 'nyassobi-wp-plugin'),
                    ],
                    'associationStatusUrl' => [
                        'type' => 'String',
                        'description' => __('URL pour les statuts associatifs.', 'nyassobi-wp-plugin'),
                    ],
                    'internalRulesUrl' => [
                        'type' => 'String',
                        'description' => __('URL pour le règlement intérieur.', 'nyassobi-wp-plugin'),
                    ],
                ],
            ]
        );

        register_graphql_field(
            'RootQuery',
            'nyassobiSettings',
            [
                'type' => 'NyassobiSettings',
                'description' => __('Paramètres disponibles pour le front-end Nyassobi.', 'nyassobi-wp-plugin'),
                'resolve' => static function (): array {
                    return Nyassobi_WP_Plugin::format_settings_for_graphql();
                },
            ]
        );
    }

    /**
     * Retrieves plugin settings as stored.
     *
     * @return array<string,string>
     */
    public static function get_settings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        if (is_array($stored)) {
            return array_map(
                static function ($value): string {
                    return (string) $value;
                },
                $stored
            );
        }

        return [];
    }

    /**
     * Formats settings for GraphQL responses.
     *
     * @return array<string,?string>
     */
    public static function format_settings_for_graphql(): array
    {
        $options = self::get_settings();

        return [
            'contactEmail' => $options['contact_email'] ?? null,
            'signupFormUrl' => $options['signup_form_url'] ?? null,
            'parentalAgreementUrl' => $options['parental_agreement_url'] ?? null,
            'associationStatusUrl' => $options['association_status_url'] ?? null,
            'internalRulesUrl' => $options['internal_rules_url'] ?? null,
        ];
    }

    /**
     * Field metadata.
     *
     * @return array<string,array<string,string>>
     */
    private function get_fields_definition(): array
    {
        return [
            'contact_email' => [
                'label' => __('Adresse email de contact', 'nyassobi-wp-plugin'),
                'description' => __('Email principal pour les demandes entrantes.', 'nyassobi-wp-plugin'),
                'type' => 'email',
            ],
            'signup_form_url' => [
                'label' => __('URL du formulaire d\'inscription', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers le formulaire d\'adhésion en ligne.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
            'parental_agreement_url' => [
                'label' => __('URL de l\'accord parental', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers le document d\'accord parental.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
            'association_status_url' => [
                'label' => __('URL des statuts associatifs', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers les statuts officiels de l\'association.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
            'internal_rules_url' => [
                'label' => __('URL du règlement intérieur', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers le règlement intérieur actualisé.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
        ];
    }
}
