<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'name'        => 'ContactEnrichment',
    'description' => 'Adds Integrations for validating, manipulating, and enhncing Contacts(Leads).',
    'version'     => '0.1',
    'author'      => 'Nicholai Bush',

    'services' => [
        'events' => [
            'mautic.enhancer.eventlistener.lead'   => [
                'class'     => \MauticPlugin\ContactEnrichmentBundle\EventListener\LeadSubscriber::class,
                'arguments' => [
                    '@mautic.enhancer.helper.enhancer',
                ],
            ],
            'mautic.enhancer.eventlistener.plugin' => [
                'class' => \MauticPlugin\ContactEnrichmentBundle\EventListener\PluginSubscriber::class,
            ],
        ],
        'models' => [
        ],
        'integrations' => [
        ],
        'other'        => [
            'mautic.helper.contactenrichment' => [
                'class'     => \MauticPlugin\ContactEnrichmentBundle\Helper\ContactEnrichmentHelper::class,
                'arguments' => [
                    '@mautic.helper.integration',
                ],
            ],
        ],
    ],
];
