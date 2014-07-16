<?php
/**
 * model exception
 * 
 */

namespace Ei\Model\Exception;

class ModelException extends \Exception
{
    
    /* >= 10000 severe issue */
    const UNDEFINED_MODEL_TYPE            = 10800;
    
    /* < 10000 important, but common exception */
    
    /* < 1000 minor exception */
}
