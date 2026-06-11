<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 確定済み・提出済みの請求期間に対する再計算を拒否したことを示す。
 * 過誤申立・返戻処理を経ずに請求明細を上書きさせないためのガード。
 */
class BillingPeriodLockedException extends RuntimeException
{
}
