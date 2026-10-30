<?php
declare(strict_types=1);

namespace TechNest\Shipping\Model;

/**
 * The 34 provinces and cities of Vietnam since 1 July 2025, with their delivery zone.
 * Old names lists the provinces merged into each one, so addresses typed before the merger still resolve.
 */
class Provinces
{
    public const COUNTRY = 'VN';

    public const NORTH = 'north';
    public const CENTRAL = 'central';
    public const SOUTH = 'south';

    /**
     * Region code => [English name, Vietnamese name, zone, old names]
     */
    private const LIST = [
        // North
        'HN' => ['Hanoi', 'Hà Nội', self::NORTH, []],
        'HP' => ['Hai Phong', 'Hải Phòng', self::NORTH, ['Hải Dương']],
        'LCU' => ['Lai Chau', 'Lai Châu', self::NORTH, []],
        'DB' => ['Dien Bien', 'Điện Biên', self::NORTH, []],
        'SL' => ['Son La', 'Sơn La', self::NORTH, []],
        'LS' => ['Lang Son', 'Lạng Sơn', self::NORTH, []],
        'QNH' => ['Quang Ninh', 'Quảng Ninh', self::NORTH, []],
        'CB' => ['Cao Bang', 'Cao Bằng', self::NORTH, []],
        'TQ' => ['Tuyen Quang', 'Tuyên Quang', self::NORTH, ['Hà Giang']],
        'LC' => ['Lao Cai', 'Lào Cai', self::NORTH, ['Yên Bái']],
        'TN' => ['Thai Nguyen', 'Thái Nguyên', self::NORTH, ['Bắc Kạn', 'Bắc Cạn']],
        'PT' => ['Phu Tho', 'Phú Thọ', self::NORTH, ['Vĩnh Phúc', 'Hòa Bình']],
        'BN' => ['Bac Ninh', 'Bắc Ninh', self::NORTH, ['Bắc Giang']],
        'HY' => ['Hung Yen', 'Hưng Yên', self::NORTH, ['Thái Bình']],
        'NB' => ['Ninh Binh', 'Ninh Bình', self::NORTH, ['Hà Nam', 'Nam Định']],
        // Central
        'TH' => ['Thanh Hoa', 'Thanh Hóa', self::CENTRAL, []],
        'NA' => ['Nghe An', 'Nghệ An', self::CENTRAL, []],
        'HT' => ['Ha Tinh', 'Hà Tĩnh', self::CENTRAL, []],
        'QT' => ['Quang Tri', 'Quảng Trị', self::CENTRAL, ['Quảng Bình']],
        'HUE' => ['Hue', 'Huế', self::CENTRAL, ['Thừa Thiên Huế']],
        'DN' => ['Da Nang', 'Đà Nẵng', self::CENTRAL, ['Quảng Nam']],
        'QNG' => ['Quang Ngai', 'Quảng Ngãi', self::CENTRAL, ['Kon Tum']],
        'GL' => ['Gia Lai', 'Gia Lai', self::CENTRAL, ['Bình Định']],
        'KH' => ['Khanh Hoa', 'Khánh Hòa', self::CENTRAL, ['Ninh Thuận']],
        'DLK' => ['Dak Lak', 'Đắk Lắk', self::CENTRAL, ['Phú Yên']],
        'LD' => ['Lam Dong', 'Lâm Đồng', self::CENTRAL, ['Đắk Nông', 'Bình Thuận']],
        // South
        'HCM' => ['Ho Chi Minh City', 'Hồ Chí Minh', self::SOUTH, [
            'HCM', 'TPHCM', 'Sài Gòn', 'Bình Dương', 'Bà Rịa - Vũng Tàu', 'Vũng Tàu',
        ]],
        'CT' => ['Can Tho', 'Cần Thơ', self::SOUTH, ['Sóc Trăng', 'Hậu Giang']],
        'DNI' => ['Dong Nai', 'Đồng Nai', self::SOUTH, ['Bình Phước']],
        'TNI' => ['Tay Ninh', 'Tây Ninh', self::SOUTH, ['Long An']],
        'VL' => ['Vinh Long', 'Vĩnh Long', self::SOUTH, ['Bến Tre', 'Trà Vinh']],
        'DT' => ['Dong Thap', 'Đồng Tháp', self::SOUTH, ['Tiền Giang']],
        'CM' => ['Ca Mau', 'Cà Mau', self::SOUTH, ['Bạc Liêu']],
        'AG' => ['An Giang', 'An Giang', self::SOUTH, ['Kiên Giang']],
    ];

    /**
     * All provinces as code => [en, vi, zone]
     *
     * @return array<string, array{en: string, vi: string, zone: string}>
     */
    public function getAll(): array
    {
        $all = [];
        foreach (self::LIST as $code => [$en, $vi, $zone]) {
            $all[$code] = ['en' => $en, 'vi' => $vi, 'zone' => $zone];
        }
        return $all;
    }

    /**
     * Zone of a region code, null when the code isn't one of ours
     *
     * @param string $code
     * @return string|null
     */
    public function getZoneByCode(string $code): ?string
    {
        return self::LIST[strtoupper(trim($code))][2] ?? null;
    }

    /**
     * Zone of a province typed as free text ("Tp. Hồ Chí Minh", "tinh binh duong", "Ha Noi"...)
     *
     * @param string $name
     * @return string|null
     */
    public function getZoneByName(string $name): ?string
    {
        $key = $this->normalize($name);
        if ($key === '') {
            return null;
        }
        foreach (self::LIST as [$en, $vi, $zone, $oldNames]) {
            foreach ([$en, $vi, ...$oldNames] as $candidate) {
                if ($this->normalize($candidate) === $key) {
                    return $zone;
                }
            }
        }
        return null;
    }

    /**
     * Lower case ASCII without spaces, the "tỉnh"/"thành phố" prefix and the "city" suffix
     *
     * @param string $name
     * @return string
     */
    private function normalize(string $name): string
    {
        $name = str_replace(['Đ', 'đ'], 'd', $name);
        $name = (string)\Normalizer::normalize($name, \Normalizer::FORM_D);
        $name = strtolower((string)preg_replace('/\p{Mn}/u', '', $name));
        $name = trim((string)preg_replace('/[^a-z0-9]+/', ' ', $name));
        $name = (string)preg_replace('/^(tinh|thanh pho|tp|t p) /', '', $name);
        $name = (string)preg_replace('/ (city|province)$/', '', $name);
        return str_replace(' ', '', $name);
    }
}
