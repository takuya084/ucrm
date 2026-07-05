<?php

namespace App\Services\Billing\Nhif;

/**
 * 国保連 交換情報ファイル（CSV形式）ビルダー
 *
 * 準拠: 障害者自立支援給付支払等システム インタフェース仕様書 共通編（令和7年4月版）1.2
 * - コントロールレコード（種別1）＋ データレコード（種別2）＋ エンドレコード（種別3）
 * - 各レコードは可変長・カンマ区切り・CRLF 終端
 * - カンマ／ダブルクォート／スペース／2バイト文字を含む項目はダブルクォートで囲む
 * - 文字コードは Shift_JIS（SJIS-win）。シングルクォート（0x27）は使用不可
 */
class NhifExchangeFileBuilder
{
    /** @var array<int, array<int, string>> */
    private array $dataRecords = [];

    /**
     * @param string $dataType     データ種別3桁（交換情報識別番号の上3桁。例: K11/K41/K61）
     * @param string $facilityCode 送付元の事業所番号10桁
     * @param string $processingYm 処理対象年月 YYYYMM（国保連で電算処理を実行する年月＝提出月）
     */
    public function __construct(
        private string $dataType,
        private string $facilityCode,
        private string $processingYm,
    ) {}

    /**
     * データレコードを追加（レコード種別・レコード番号は出力時に付加する）
     *
     * @param array<int, string|int|null> $fields 交換情報の「項目説明」順の値。null は省略項目
     */
    public function addRecord(array $fields): void
    {
        $this->dataRecords[] = array_map(
            fn ($v) => $v === null ? '' : (string) $v,
            $fields
        );
    }

    public function recordCount(): int
    {
        return count($this->dataRecords);
    }

    /**
     * Shift_JIS のファイル内容を生成
     */
    public function render(): string
    {
        $lines = [];

        // コントロールレコード（項番11 予備は設定しない＝末尾カンマのみ）
        $control = [
            '1',
            '1',
            '0',
            (string) count($this->dataRecords),
            $this->dataType,
            '0',
            $this->facilityCode,
            '0',
            '1', // 媒体区分: 1=伝送
            $this->processingYm,
            '', // 予備
        ];
        $lines[] = implode(',', array_map([$this, 'quote'], $control));

        $recordNo = 1;
        foreach ($this->dataRecords as $fields) {
            $recordNo++;
            $record = array_merge(['2', (string) $recordNo], $fields);
            $lines[] = implode(',', array_map([$this, 'quote'], $record));
        }

        // エンドレコード
        $recordNo++;
        $lines[] = '3,' . $recordNo;

        $content = implode("\r\n", $lines) . "\r\n";

        return mb_convert_encoding($content, 'SJIS-win', 'UTF-8');
    }

    /**
     * 項目の引用符処理（共通編 1.2.2(4) 特記事項）
     */
    private function quote(string $value): string
    {
        // 使用不可能文字（シングルクォート）を除去
        $value = str_replace("'", '', $value);

        if ($value === '') {
            return '';
        }

        $needsQuote = strpbrk($value, '," ') !== false
            || strlen($value) !== mb_strlen($value, 'UTF-8'); // 2バイト文字を含む

        if (!$needsQuote) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * カナ項目（英数属性）: 全角かな・カナ・英数を半角化し、最大バイト数（SJIS）で切り詰め
     */
    public static function kana(?string $value, int $maxBytes): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // 全角カタカナ→半角(k)、全角ひらがな→半角カタカナ(h)、全角英数→半角(a)、全角スペース→半角(s)
        $converted = mb_convert_kana($value, 'khas', 'UTF-8');
        $converted = str_replace([' ', "'"], ['', ''], trim($converted));

        $sjis = mb_convert_encoding($converted, 'SJIS-win', 'UTF-8');
        if (strlen($sjis) > $maxBytes) {
            $sjis = mb_strcut($sjis, 0, $maxBytes, 'SJIS-win');
        }

        return mb_convert_encoding($sjis, 'UTF-8', 'SJIS-win');
    }

    /**
     * 時刻 HH:MM:SS / HH:MM → HHMM（4桁）
     */
    public static function hhmm(?string $time): string
    {
        if (!$time) {
            return '';
        }

        return substr(str_replace(':', '', $time), 0, 4);
    }

    /**
     * 小数値 → 整数部+小数部の固定スケール表現（例: 1.5時間, scale=2 → "150"）
     */
    public static function scaled(?float $value, int $scale): string
    {
        if ($value === null) {
            return '';
        }

        return (string) (int) round($value * (10 ** $scale));
    }
}
