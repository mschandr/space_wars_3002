<?php

namespace App\Faker;

use Faker\Provider\Base;


class SpaceProvider extends Base
{

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
