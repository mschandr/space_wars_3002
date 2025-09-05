<?php

namespace App\Faker;

use Faker\Provider\Base;


class SpaceProvider extends Base
{

    /**
     * @var array|string[]
     */
    protected static array $galaxyName = [
        "Andromeda", "Cygnus", "Orion", "Draco", "Scorpius",
        "Cassiopeia", "Pegasus", "Hydra", "Ursa Major", "Canis Major",
        "Lyra", "Taurus", "Leo", "Phoenix", "Centaurus", "Sagittarius",
        "Nebula", "Pulsar", "Quasar", "Horizon", "Expanse", "Rift"
    ];
    /**
     * @var array|string[]
     */
    protected static array $galaxyVerbs = [
        "Rising", "Ascending", "Awakening", "Expanding", "Surging", "Spreading",
        "Fading", "Burning", "Collapsing", "Dying", "Darkening", "Fracturing",
        "Ignited", "Energized", "Pulsing", "Roaring", "Blazing", "Radiating",
        "Whispering", "Shrouded", "Hidden", "Forgotten", "Veiled", "Forbidden"
    ];
    /**
     * @var array|string[]
     */
    protected static array $galaxySuffix = [
        "", " of Twilight", " of Shadows", " of Dawn", " of Silence", " of Eternity"
    ];

    /**
     * @var array|string[]
     */

    /**
     * @var array|string[]
     */
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
    /**
     * @var array|string[]
     */
    protected static array $nebulae = [
        'Pleiades', 'Lagoon', 'Trifid', 'Cone', 'Butterfly', "Cat's Eye", "Little Ghost", "Reflective",
        'Butterfly', 'Ghost of Jupiter', 'Blue Snowman', 'Flaming Star', 'Southern Crab', 'Monkey Head',
        'Waterfall', 'Orion', 'Crab', 'Carnia', 'Ring', 'Eagle', 'Lagoon', 'Horsehead', 'Tarantula', 'Helix',
    ];
    protected static array $arabic_numerals = [
        '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
        '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
        '31', '32', '33', '34', '35', '36', '37', '38', '39', '40',
        '41', '42', '43', '44', '45', '46', '47', '48', '49', '50'
    ];
    protected static array $astronomers = [
        'Bartelman', 'Blandford', 'Boesgaard', 'Bond', 'Bosma', 'Bouwens', 'Brandt', 'Brown', 'Bruzual', 'Burgasser',
        'Caldwell', 'Carr', 'Carroll', 'Casertano', 'Cen', 'Charlot', 'Charbonneau', 'Cheng', 'Chiosi', 'Churchwell',
        'Ciotti', 'Clavel', 'Clements', 'Cohen', 'Cole', 'Colless', 'Conselice', 'Cook', 'Cooper', 'Corbelli', 'Cordes',
        'Cowan', 'Cropper', 'Cutri', 'Dalcanton', 'Davies', 'Davis', 'De Vaucouleurs', 'Dekel', 'Dekleva', 'Dressler',
        'Dunlop', 'Durret', 'Ebeling', 'Eisenstein', 'Elvis', 'Elston', 'Faber', 'Fall', 'Fan', 'Fazio', 'Ferguson',
        'Ferrarese', 'Fields', 'Filippenko', 'Forbes', 'Ford', 'Forman', 'Fouque', 'Frank', 'Fukugita', 'Gallagher',
        'Garnica', 'Gates', 'Gawiser', 'Geller', 'Gehrels', 'Gelman', 'Germain', 'Gilmore', 'Glazebrook', 'Gonzalez',
        'Gott', 'Gould', 'Graham', 'Granato', 'Green', 'Greenstein', 'Grillmair', 'Gunn', 'Haehnelt', 'Hakkila', 'Hall',
        'Halpern', 'Hamilton', 'Hartigan', 'Hartmann', 'Hauser', 'Heavens', 'Heckman', 'Heisler', 'Helmi', 'Hernquist',
        'Heyl', 'Hill', 'Hoag', 'Hoessel', 'Hogg', 'Hopkins', 'Horne', 'Hough', 'Houk', 'Howell', 'Hoyle', 'Hubbell',
        'Hughes', 'Hui', 'Hultman', 'Impey', 'Ivezic', 'Jackson', 'Jaffe', 'James', 'Jansen', 'Jarvis', 'Jenkins',
        'Jensen', 'Jerjen', 'Jimenez', 'Jones', 'Jorgensen', 'Joseph', 'Joung', 'Jura', 'Kaiser', 'Kalirai',
        'Kamionkowski', 'Kaplan', 'Kauffmann', 'Kauffmann', 'Kennicutt', 'Kent', 'Kereš', 'Kerr', 'Kessler', 'Kim',
        'Kinney', 'Kirk', 'Knapp', 'Kollmeier', 'Koo', 'Korista', 'Kormendy', 'Kowalski', 'Kochanek', 'Kravtsov',
        'Kron', 'Kudritzki', 'Kulkarni', 'Kunth', 'Kuranz', 'Laird', 'Lamb', 'Langer', 'Larson', 'Lauer', 'Lawrence',
        'Leitherer', 'Leonard', 'Lilly', 'Lin', 'Liske', 'Livio', 'Loeb', 'Longair', 'Lopez', 'Lubin', 'Lucy', 'Lupton',
        'Lynden-Bell', 'Madau', 'Magorrian', 'Magnier', 'Malkan', 'Malmquist', 'Mann', 'Marcy', 'Martel', 'Martinez',
        'Mathews', 'Mathis', 'Mattila', 'Maunder', 'Mazzali', 'McAlpine', 'McCrea', 'McHardy', 'McKee', 'McLaughlin',
        'McMahon', 'McNamara', 'Meadows', 'Meisenheimer', 'Mellier', 'Merritt', 'Mestel', 'Metzger', 'Meyer',
        'Mihalas', 'Miller', 'Mills', 'Mirabel', 'Mo', 'Mohr', 'Molnar', 'Monet', 'Moore', 'Morris', 'Morrison',
        'Mould', 'Mould', 'Murphy', 'Murray', 'Nagashima', 'Nakagawa', 'Nakamura', 'Narayanan', 'Navarro',
        'Neugebauer', 'Newman', 'Nicastro', 'Ninkov', 'Norman', 'Nugent', 'O\'Connell', 'O\'Dell', 'Ohashi', 'Oke',
        'Olive', 'Olling', 'Ostriker', 'Paczynski', 'Pagel', 'Palomar', 'Pan', 'Parker', 'Peacock', 'Peebles',
        'Penston', 'Perlmutter', 'Peterson', 'Phillips', 'Pierer', 'Pietronero', 'Pilkington', 'Pinfield', 'Piran',
        'Plait', 'Pogge', 'Polletta', 'Postman', 'Press', 'Prieto', 'Pringle', 'Prosser', 'Puetter', 'Quillen',
        'Racine', 'Radford', 'Rees', 'Reid', 'Rich', 'Richer', 'Rix', 'Robinson', 'Roche', 'Rodgers', 'Rood', 'Rosati',
        'Rosen', 'Rosner', 'Roth', 'Rowan-Robinson', 'Rubin', 'Rudnick', 'Ruelas-Mayorga', 'Ruiz', 'Salpeter',
        'Sandage', 'Sargent', 'Sarajedini', 'Sasaki', 'Savage', 'Schade', 'Schechter', 'Schlegel', 'Schneider',
        'Schombert', 'Schulte-Ladbeck', 'Scoville', 'Searle', 'Seaton', 'Seitzer', 'Sellwood', 'Shapley', 'Shapiro',
        'Sharp', 'Shaw', 'Shectman', 'Sheth', 'Shlosman', 'Shu', 'Silk', 'Simcoe', 'Simon', 'Small', 'Smith', 'Sneden',
        'Somerville', 'Sparke', 'Spinrad', 'Squires', 'Stalder', 'Stanek', 'Steidel', 'Stein', 'Steinmetz', 'Stern',
        'Stetson', 'Stiavelli', 'Still', 'Stockton', 'Storchi-Bergmann', 'Storey', 'Strauss', 'Strom', 'Struble',
        'Suntola', 'Sutherland', 'Szalay', 'Tacconi', 'Tanaka', 'Tarter', 'Taylor', 'Tegmark', 'Terlevich', 'Thakar',
        'Thompson', 'Thornley', 'Toomre', 'Torres', 'Tremaine', 'Trimble', 'Turner', 'Tyson', 'Van den Bergh',
        'Van der Kruit', 'Van Dokkum', 'Van Gorkom', 'Van Paradijs', 'Van Waerbeke', 'Vanden Berk', 'Vandenbussche',
        'Vazdekis', 'Veron', 'Veron-Cetty', 'Villata', 'Vogt', 'Wakamatsu', 'Walker', 'Wall', 'Wallerstein', 'Walsh',
        'Walterbos', 'Wampler', 'Wang', 'Wasserburg', 'Watson', 'Webb', 'Weinberg', 'Weir', 'Weiss', 'White',
        'Whitmore', 'Wickramasinghe', 'Wilkinson', 'Williams', 'Wilson', 'Wold', 'Wolfe', 'Woltjer', 'Wood', 'Woodruff',
        'Worrall', 'Wright', 'Wyse', 'Yahil', 'York', 'Young', 'Zaldarriaga', 'Zehavi', 'Zeldovich', 'Zentner', 'Zhang',
        'Zhao', 'Zheng', 'Zhou', 'Zucker', 'Zwaan'
    ];

    public function unknownComet(): string
    {
        return "Unknown Comet";
    }

    /**
     * @return string
     */
    public function nebulaeName(): string
    {
        //return static::randomElement(static::$nebulae) . " Nebula " . static::randomElement(static::$greekLetters) .
            " " . static::randomElement(static::$romanNumerals);
    }

    /**
     * @return string
     */
    public function asteroidName(): string
    {
        //return "asteroid " . static::randomElement(static::$greekLetters);
    }

    public function cometName(): string
    {
        return "Comet " .
            static::randomElement(static::$astronomers) . " " .
            static::randomElement(static::$arabic_numerals);
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

    public function asteroidbeltName(): string
    {
        return "the asteroid belt";
    }

    public function blackholeName(int $x, int $y): string
    {
        return "Blackhole $x$y";
    }

    // @todo fill this in
    public function dwarfplanetName(): string
    {
        return "";
    }

    public function supermassiveblackholeName(int $x, int $y): string
    {
        return "Supermassive Blackhole $x $y";
    }
}
