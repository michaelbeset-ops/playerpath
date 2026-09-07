<?php

namespace App\Support\Status;

use RuntimeException;

/** Een overgang die de statusmachine niet toestaat. */
class TransitionException extends RuntimeException {}
