<?php

namespace App\Services\Accounting\SafeDelete;

use JsonSerializable;

final readonly class DeletionPlan implements JsonSerializable
{
    /**
     * @param array<int, array{label:string,count:int,effect:string}> $dependencies
     */
    public function __construct(
        public string $entityType,
        public string $entityLabel,
        public array $dependencies,
        public string $deleteEffect = 'The selected record will be permanently deleted from the database.',
        public ?string $confirmationText = null,
    ) {}

    public function hasDependencies(): bool
    {
        return $this->dependencies !== [];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_label' => $this->entityLabel,
            'has_dependencies' => $this->hasDependencies(),
            'dependencies' => $this->dependencies,
            'delete_effect' => $this->deleteEffect,
            'confirmation_text' => $this->confirmationText ?? ($this->hasDependencies()
                ? 'Related records will lose this relationship and will be made inactive or incomplete until they are edited and linked to a valid replacement.'
                : 'No dependent records were found.'),
        ];
    }
}
