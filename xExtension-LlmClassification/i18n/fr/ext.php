<?php

return array(
	'llm_classification' => array(
		'api' => array(
			'title' => 'Configuration de l’API',
			'url' => 'URL de l’API',
			'url_help' => 'URL de base compatible OpenAI (ex. <code>https://api.openai.com/v1</code>)',
			'key' => 'Clé API',
			'key_help' => 'Jeton pour l’authentification',
			'model' => 'Modèle',
			'model_help' => 'Nom du modèle (ex. gpt-4o-mini)',
			'timeout' => 'Délai d’attente (secondes)',
			'max_retries' => 'Tentatives max.',
			'max_retries_help' => 'Nombre de nouvelles tentatives en cas d’erreur transitoire (délai dépassé, réponse invalide, 500). 0 = pas de nouvelle tentative.',
		),
		'prompts' => array(
			'title' => 'Invites',
			'system' => 'Invite système (lecture seule)',
			'user' => 'Invite utilisateur',
			'user_help' => 'Variables disponibles : <code>{title}</code>, <code>{content}</code>, <code>{author}</code>, <code>{url}</code>, <code>{feed_url}</code>, <code>{feed_name}</code>, <code>{date}</code>, <code>{tags}</code>',
			'max_content_length' => 'Longueur max. du contenu (caractères)',
			'max_content_length_help' => 'Nombre maximum de caractères pour la variable {content} (0 = illimité)',
		),
		'tags' => array(
			'title' => 'Classification par tags',
			'enable' => 'Activer la classification par tags',
			'prefix' => 'Préfixe des tags',
			'prefix_help' => 'Préfixe ajouté à chaque tag issu du LLM (ex. « llm/ »)',
			'allowed' => 'Tags autorisés (un par ligne)',
			'allowed_help' => 'Si renseigné, seuls ces tags seront acceptés dans la réponse du LLM. Vide = tout autorisé.',
		),
		'filter' => array(
			'title' => 'Conditions pour l’étiquetage',
			'search' => 'Filtres de recherche',
			'search_help' => 'Classifier uniquement les articles correspondant à au moins un de ces filtres. Laisser vide pour classifier tous les articles.',
		),
		'default_prompt' => 'Classifie l’article suivant.

Titre : {title}
Auteur : {author}
Date : {date}
URL : {url}
Flux : {feed_name} ({feed_url})
tags existantes : {tags}

Contenu :
{content}',
	),
);
