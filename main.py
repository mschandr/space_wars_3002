import random

class Universe:
    def __init__(self, num_stars=100):
        self.num_stars = num_stars
        self.stars = self.generate_stars()

    def generate_stars(self):
        stars = []
        for _ in range(self.num_stars):
            x, y, z = random.uniform(0, 1000), random.uniform(0, 1000), random.uniform(0, 1000) # 3D coordinates
            star = Star.generate_random(x, y, z)
            stars.append(star)
        return stars

    def display(self):
        for star in self.stars:
            print(star)

class Star:
    STAR_TYPES = ["O", "B", "A", "F", "G", "K", "M"]

    def __init__(self, x, y, z, star_type, has_solar_system=False):
        self.x = x
        self.y = y
        self.z = z
        self.star_type = star_type
        self.has_solar_system = has_solar_system
        self.planets = []
        if has_solar_system:
            self.planets = PlanetGenerator.generate_planets(star_type)

    @classmethod
    def generate_random(cls, x, y, z):
        star_type = random.choices(cls.STAR_TYPES, weights=[1, 2, 3, 5, 7, 10, 15])[0]  # Bias toward smaller stars
        has_solar_system = random.random() < 0.5  # 50% chance of having planets
        star = cls(x, y, z, star_type, has_solar_system)
        if has_solar_system:
            star.planets = PlanetGenerator.generate_planets()
        return star

    def __repr__(self):
        return f"Star({self.star_type}) at ({self.x:.2f}, {self.y:.2f}, {self.z:.2f}) - {'Has planets' if self.has_solar_system else 'No planets'}"

class PlanetGenerator:
    PLANET_TYPES = ["Rocky", "Gas Giant", "Dwarf Planet", "Giant Earth", "Lava", "Super Dense", "Asteroid Belt", "Oort Cloud"]

    @staticmethod
    def generate_planets():
        # Define the number of planetary bodies based on the star type
        star_size_factors = {
            "O": (10, 20),  # Largest stars tend to have more planetary bodies
            "B": (8, 18),
            "A": (6, 16),
            "F": (6, 14),
            "G": (5, 12),
            "K": (4, 10),
            "M": (4, 8),  # Smallest stars tend to have fewer planetary bodies
        }
        min_planets, max_planets = star_size_factors.get(star_type, (4, 10))
        num_planets = random.randint(min_planets, max_planets)

        return [random.choice(PlanetGenerator.PLANET_TYPES) for _ in range(num_planets)]

if __name__ == "__main__":
    universe = Universe(num_stars=20)
    universe.display()

