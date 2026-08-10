<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

final class LibraryPostTypes
{
    public const PROJECT = 'verbum_project';
    public const BOOK = 'verbum_book';
    public const CHAPTER = 'verbum_chapter';
    public const RESEARCH = 'verbum_research';

    public function register(): void
    {
        register_post_type(self::PROJECT, [
            'label' => 'Projetos Verbum',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type(self::BOOK, [
            'label' => 'Obras Verbum',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type(self::CHAPTER, [
            'label' => 'Capítulos Verbum',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type(self::RESEARCH, [
            'label' => 'Fontes de Pesquisa Verbum',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }
}
