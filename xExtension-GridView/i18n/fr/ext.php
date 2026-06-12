<?php

return array(
	'gridview' => array(
		'view_mode_name' => 'Vue Grille',
		'config' => array(
			'columns' => 'Nombre de colonnes',
			'columns_label' => 'colonnes',
			'columns_help' => 'Choisissez le nombre de colonnes à afficher dans la vue grille (2-4). Sur les petits écrans, cela s\'ajustera automatiquement.',
			'fetch_og_image' => 'Récupération des miniatures',
			'fetch_og_image_label' => 'Récupérer les miniatures depuis les pages des articles',
			'fetch_og_image_help' => 'Lorsque cette option est activée, l\'extension récupère les images Open Graph des pages d\'articles qui n\'ont pas de miniature dans le flux RSS. Cela améliore les images des cartes mais peut ralentir le chargement des pages.',
			'sort_by_date' => 'Tri par défaut',
			'sort_by_date_label' => 'Trier par date de publication (plus récent en premier)',
			'sort_by_date_help' => 'Lorsque cette option est activée, l\'ordre de tri par défaut est défini sur la date de publication, du plus récent au plus ancien. Ceci est appliqué une seule fois ; vous pouvez toujours modifier l\'ordre de tri manuellement dans FreshRSS.',
			'mobile_menu_button' => 'Bouton du panneau latéral',
			'mobile_menu_button_label' => 'Afficher un bouton flottant pour le panneau latéral',
			'mobile_menu_button_help' => 'Lorsque cette option est activée, un bouton hamburger flottant apparaît en bas à gauche de l\'écran pour ouvrir le panneau latéral de FreshRSS.',
			'sticky_nav' => 'Barre de navigation fixe',
			'sticky_nav_label' => 'Garder la barre de navigation visible lors du défilement',
			'sticky_nav_help' => 'Lorsque cette option est activée, la barre de navigation (avec lu/non lu, favoris, etc.) reste fixée en haut lors du défilement des articles en mode grille.',
			'usage_title' => 'Comment utiliser',
			'usage_info' => 'Après avoir enregistré vos paramètres, cliquez sur l\'icône grille (▦) dans l\'en-tête ou appuyez sur « G » sur votre clavier pour basculer la vue grille. Cliquez sur n\'importe quelle carte pour ouvrir l\'article dans un nouvel onglet.',
		),
	),
);
