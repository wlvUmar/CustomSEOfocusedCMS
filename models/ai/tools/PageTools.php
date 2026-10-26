<?php
// path: ./models/ai/tools/PageTools.php
// Facade over PageReader / PageSectionEditor / PageRevisionTools (hygiene P1 split).
// Tool names, schemas, and behaviour are unchanged -- AiToolRegistry still uses this class.

require_once BASE_PATH . '/models/ai/tools/PageReader.php';
require_once BASE_PATH . '/models/ai/tools/PageSectionEditor.php';
require_once BASE_PATH . '/models/ai/tools/PageRevisionTools.php';

class PageTools {

    /** Fields the agent may write to. Kept in sync with the page editor's AI allowlist. */
    public const FIELDS = [
        'content_ru', 'content_uz',
        'title_ru', 'title_uz',
        'meta_title_ru', 'meta_title_uz',
        'meta_description_ru', 'meta_description_uz',
    ];

    public static function definitions(): array {
        return array_merge(
            PageReader::definitions(),
            PageSectionEditor::definitions(),
            PageRevisionTools::definitions()
        );
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'list_pages':
            case 'get_page':
            case 'search_content':
            case 'list_sections':
            case 'get_section':
            case 'get_content_chunk':
                return PageReader::handle($name, $args);
            case 'str_replace_field':
            case 'set_field':
            case 'insert_section':
            case 'update_section':
            case 'patch_section':
            case 'set_section_style':
            case 'wrap_section':
            case 'add_section_marker':
            case 'auto_sectionize':
            case 'set_custom_css':
            case 'set_page_theme':
            case 'set_page_style':
            case 'batch_update':
                return PageSectionEditor::handle($name, $args);
            case 'list_page_revisions':
            case 'get_page_revision':
            case 'restore_page_revision':
                return PageRevisionTools::handle($name, $args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    /**
     * Split an HTML field on its "<!-- Section Name -->" comment markers
     * Delegated to PageSectionsHelper (02-architecture #3) -- facade kept for BC.
     */
    public static function splitIntoSections(string $html): array {
        return PageSectionsHelper::splitIntoSections($html);
    }

    /** Truncate long string fields to keep tool results small enough to feed back to the model. */
    public static function clipRow(array $row, array $limits): array {
        return PageReader::clipRow($row, $limits);
    }
}
