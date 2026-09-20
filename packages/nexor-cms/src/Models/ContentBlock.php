<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Support\Nexor;

/**
 * Правка блока, сделанная на сайте: ключ и то, чем заменили шаблонное значение.
 */
#[Fillable(['key', 'type', 'value', 'updated_by'])]
class ContentBlock extends Model
{
    protected $table = 'content_blocks';

    /**
     * @return BelongsTo<NexorUser, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(Nexor::userModel(), 'updated_by');
    }
}
