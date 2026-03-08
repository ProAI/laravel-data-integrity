<?php

namespace ProAI\DataIntegrity;

use Closure;

class Audit
{
    /**
     * The description of this audit.
     */
    protected ?string $description = null;

    /**
     * The query callback for scoping the audit.
     */
    protected ?Closure $queryCallback = null;

    /**
     * The chunk size for this audit.
     */
    protected int $chunkSize;

    /**
     * The callback to run before processing a chunk.
     */
    protected ?Closure $beforeCallback = null;

    /**
     * The callback to run after processing a chunk.
     */
    protected ?Closure $afterCallback = null;

    /**
     * The validation callback.
     */
    protected ?Closure $validateCallback = null;

    /**
     * Create a new Audit instance.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public function __construct(
        protected string $model,
    ) {
        $this->chunkSize = AuditManager::getDefaultChunkSize();
    }

    /**
     * Set the description of this audit.
     *
     * @return $this
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the query callback for scoping the audit.
     *
     * @return $this
     */
    public function query(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Set the validation callback.
     *
     * @return $this
     */
    public function validate(Closure $callback): static
    {
        $this->validateCallback = $callback;

        return $this;
    }

    /**
     * Set the chunk size for this audit.
     *
     * @return $this
     */
    public function chunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Set the callback to run before processing a chunk.
     *
     * @return $this
     */
    public function before(Closure $callback): static
    {
        $this->beforeCallback = $callback;

        return $this;
    }

    /**
     * Set the callback to run after processing a chunk.
     *
     * @return $this
     */
    public function after(Closure $callback): static
    {
        $this->afterCallback = $callback;

        return $this;
    }

    /**
     * Get the description of this audit.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the model class this audit operates on.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the query callback.
     */
    public function getQueryCallback(): ?Closure
    {
        return $this->queryCallback;
    }

    /**
     * Get the chunk size.
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Get the before callback.
     */
    public function getBeforeCallback(): ?Closure
    {
        return $this->beforeCallback;
    }

    /**
     * Get the after callback.
     */
    public function getAfterCallback(): ?Closure
    {
        return $this->afterCallback;
    }

    /**
     * Get the validate callback.
     */
    public function getValidateCallback(): ?Closure
    {
        return $this->validateCallback;
    }
}
