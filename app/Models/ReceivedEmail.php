<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated Use TestResult instead - this model points to the test_results table
 * ReceivedEmail is now an alias for TestResult for backward compatibility
 */
class ReceivedEmail extends TestResult
{
    // Force this alias to use the test_results table
    protected $table = 'test_results';
    
    // This class extends TestResult to maintain backward compatibility
    // All functionality is inherited from TestResult
    // 
    // TODO: Replace all uses of ReceivedEmail with TestResult
    // TODO: Remove this class once all references are updated
}