<?php

namespace Flat3\Lodata\Interfaces;

/**
 * Title Interface
 * @package Flat3\Lodata\Interfaces
 */
interface TitleInterface
{
    /**
     * Get the title of this object
     * @return string|null
     */
    public function getTitle(): ?string;

    /**
     * Set the title of this object
     * @param  string  $title  Title
     * @return mixed
     */
    public function setTitle(string $title);
}