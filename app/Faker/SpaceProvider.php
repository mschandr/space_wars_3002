<?php

namespace App\Faker;

use Faker\Provider\Base;

class SpaceProvider extends Base
{
    protected static array $romanNumerals = [
        'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
        'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX',
        'XXI', 'XXII', 'XXIII', 'XXIV', 'XXV', 'XXVI', 'XXVII', 'XXVIII', 'XXIX', 'XXX',
        'XXXI', 'XXXII', 'XXXIII', 'XXXIV', 'XXXV', 'XXXVI', 'XXXVII', 'XXXVIII', 'XXXIX', 'XL',
        'XLI', 'XLII', 'XLIII', 'XLIV', 'XLV', 'XLVI', 'XLVII', 'XLVIII', 'XLIX', 'L',
    ];
    protected static array $greekLetters = [
        'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu',
        'Xi', 'Omicron', 'Pi', 'Rho', 'Sigma', 'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega'
    ];

    protected static array $stars = [
        'Proxima Centauri', 'Alpha Centauri A', 'Alpha Centauri B', "Barnard's Star", 'Wolf 359', 'Lalande 21185',
        'Sirius A', 'Sirius B', 'Luyten 726-8', 'Ross 154', 'Epsilon Eridani', '61 Cygni A', '61 Cygni B',
        'Epsilon Indi', 'Procyon A', 'Procyon B', 'Groombridge 1618', 'DX Cancri', "Teegarden's Star", 'GJ 1051',
        "L 725-88", "Krüger 60A", 'Tau Ceti', 'WISE 0833+4902', 'Stein 2051', "Kapteyn's Star", 'SCR 1845-6357',
        'GJ 3133', 'LTT 1445', 'GJ 1240', 'WISE 0647-6242', 'WISE 1506+7027', 'GJ 843', 'Gliese 687', '40 Eridani A',
        '40 Eridani B', 'HIP 70828', 'GJ 887', 'GJ 1152', 'GJ 205', 'GJ 412', 'GJ 1005', 'GJ 629', 'LTT 1235',
        'GJ 806', 'WISE 0350-5627', 'GJ 1267', 'GJ 380', 'GJ 272', 'HIP 11662', 'GJ 674', 'GJ 390', 'GJ 876',
        'WISE 0202+6048', 'GJ 831', 'GJ 251', 'GJ 433', 'GJ 1069', 'Deneb Kaitos', 'LP 686-60', 'GJ 696', 'GJ 849',
        'WISE 1237-6337', 'GJ 1245', 'GJ 406', 'SCR 1845-6358', 'WISE 0855+0145', 'GJ 270', 'HIP 79977', 'GJ 1002',
        'SCR 1845-6359', 'GJ 832', 'GJ 388', 'GJ 403', 'GJ 848', 'GJ 1281', 'SCR 1645-9276', 'GJ 387', 'GJ 207',
        'WISE 0359-3602', 'GJ 1252', 'GJ 1116', 'GJ 411', 'GJ 357', 'GJ 803', 'SCR 1845-6360', 'GJ 1071',
        'SCR 1645-9277', 'GJ 441', 'GJ 1283', "Teegarden's Star b", 'SCR 1845-6362', 'GJ 385', 'GJ 1248', 'GJ 404',
    ];

    protected static array $nebulae = [
        'Pleiades', 'Lagoon', 'Trifid', 'Cone', 'Butterfly', "Cat's Eye", "Little Ghost", "Reflective",
        'Butterfly', 'Ghost of Jupiter', 'Blue Snowman', 'Flaming Star', 'Southern Crab', 'Monkey Head',
        'Waterfall', 'Orion', 'Crab', 'Carnia', 'Ring', 'Eagle', 'Lagoon', 'Horsehead', 'Tarantula', 'Helix',
    ];

    public function starName(): string
    {
        return static::randomElement(static::$stars)." ".static::randomElement(static::$greekLetters).
            " ".static::randomElement(static::$romanNumerals);
    }

    public function unknownStar(): string
    {
        return "Unknown Star System";
    }

    public function nebulaeName(): string
    {
        return static::randomElement(static::$nebulae)." Nebula ".static::randomElement(static::$greekLetters).
            " ".static::randomElement(static::$romanNumerals);
    }

    // @todo fill this in
    public function asteroidName(): string
    {
        return "";
    }

    // @todo fill this in
    public function cometName(): string
    {
        return "";
    }

    // @todo fill this in
    public function moonName(): string
    {
        return "";
    }

    // @todo fill this in
    public function planetName(): string
    {
        return "";
    }

    // @todo fill this in
    public function asteroidbeltName(): string
    {
        return "";
    }

    // @todo fill this in
    public function blackholeName(): string
    {
        return "";
    }

    // @todo fill this in
    public function dwarfplanetName(): string
    {
        return "";
    }

    // @todo file this in
    public function supermassiveblackholeName(): string
    {
        return "";
    }
}
