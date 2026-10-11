<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Melasistema\MelaCms\Filament\Forms\Translations;

/**
 * A form as a site resource would build it, without the resource around it.
 */
class ArticleForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $articleId = null;

    public function mount(?int $articleId = null): void
    {
        $this->articleId = $articleId;

        $this->form->fill($articleId === null ? [] : Article::findOrFail($articleId)->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translations::make([
                    TextInput::make('title')->required()->maxLength(20),
                    Textarea::make('body'),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $article = $this->articleId === null ? new Article : Article::findOrFail($this->articleId);

        $article->fill($this->form->getState())->save();

        $this->articleId = $article->getKey();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
