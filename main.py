import random
import sys
import pandas as pd
from flask import Flask, render_template_string

# Global mineral reserves
GLOBAL_RESOURCES = {
    "Platinum": 500_000_000_000,  # 500 billion units
    "Iron": 1_000_000_000_000,    # 1 trillion units
    "Hydrogen": 2_000_000_000_000, # 2 trillion units
    "Nickel": 750_000_000_000,
    "Titanium": 600_000_000_000
}

app = Flask(__name__)

class Universe:
    def __init__(self, num_stars=100):
        self.num_stars = num_stars
        self.stars = self.generate_stars()

    def generate_stars(self):
        stars = []
        for _ in range(self.num_stars):
            x, y, z = random.uniform(0, 1000), random.uniform(0, 1000), random.uniform(0, 1000)  # 3D coordinates
            star = Star.generate_random(x, y, z)
            stars.append(star)
        return stars

    def generate_star_system_data(self):
        data = []
        universe_totals = {mineral: 0 for mineral in GLOBAL_RESOURCES.keys()}

        for star in self.stars:
            system_totals = {mineral: 0 for mineral in GLOBAL_RESOURCES.keys()}
            planets_info = []
            planets_data = []

            for i, planet in enumerate(star.planets):
                planet_entry = {"Planet": f"Planet {i+1}"}
                for mineral, amount in planet['minerals'].items():
                    system_totals[mineral] += amount
                    universe_totals[mineral] += amount
                    planet_entry[mineral] = f"{amount:,}"
                planets_data.append(planet_entry)

            system_totals_text = {mineral: f"{amount:,} ({(amount / GLOBAL_RESOURCES[mineral]) * 100:.2f}%)" for mineral, amount in system_totals.items() if amount > 0}

            data.append({
                "Star Coordinates": f"({star.x:.2f}, {star.y:.2f}, {star.z:.2f})",
                "Planets": planets_data,
                "System Totals": system_totals_text
            })

        universe_totals_text = {mineral: f"{amount:,} ({(amount / GLOBAL_RESOURCES[mineral]) * 100:.2f}%)" for mineral, amount in universe_totals.items() if amount > 0}
        return data, universe_totals_text

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
        return cls(x, y, z, star_type, has_solar_system)

class PlanetGenerator:
    PLANET_TYPES = ["Rocky", "Gas Giant", "Dwarf Planet", "Giant Earth", "Lava", "Super Dense", "Asteroid Belt", "Oort Cloud"]
    PLANET_MINERAL_DISTRIBUTION = {
        "Rocky": {"Iron": 40, "Nickel": 30, "Titanium": 10},
        "Gas Giant": {"Hydrogen": 70, "Platinum": 5},
        "Dwarf Planet": {"Nickel": 50, "Platinum": 10},
        "Giant Earth": {"Iron": 50, "Titanium": 20},
        "Lava": {"Titanium": 50, "Platinum": 15},
        "Super Dense": {"Platinum": 30, "Nickel": 40},
        "Asteroid Belt": {"Iron": 20, "Platinum": 50},
        "Oort Cloud": {"Hydrogen": 80}
    }

    @staticmethod
    def generate_planets(star_type):
        num_planets = random.randint(4, 20)
        planets = []
        for _ in range(num_planets):
            planet_type = random.choice(PlanetGenerator.PLANET_TYPES)
            minerals = PlanetGenerator.assign_minerals(planet_type)
            planets.append({"type": planet_type, "minerals": minerals})
        return planets

    @staticmethod
    def assign_minerals(planet_type):
        minerals = {}
        if planet_type in PlanetGenerator.PLANET_MINERAL_DISTRIBUTION:
            for mineral, percentage in PlanetGenerator.PLANET_MINERAL_DISTRIBUTION[planet_type].items():
                minerals[mineral] = int(GLOBAL_RESOURCES[mineral] * (percentage / 10000))  # Small fraction assigned per planet
        return minerals

@app.route('/')
def home():
    universe = Universe(num_stars=20)
    star_systems, universe_totals_text = universe.generate_star_system_data()

    html_template = """
    <html>
    <head><title>Generated Universe</title></head>
    <body>
        <h1>Star Systems</h1>
        {% for system in star_systems %}
        <h2>Star Coordinates: {{ system['Star Coordinates'] }}</h2>
        <table border='1'>
            <tr><th>Planet</th><th>Minerals</th></tr>
            {% for planet in system['Planets'] %}
            <tr>
                <td>{{ planet['Planet'] }}</td>
                <td>{% for mineral, amount in planet.items() if mineral != 'Planet' %}{{ mineral }}: {{ amount }}<br>{% endfor %}</td>
            </tr>
            {% endfor %}
        </table>
        <p><b>System Totals:</b> {{ system['System Totals'] }}</p>
        {% endfor %}
        <h2>Total Minerals in Universe</h2>
        <p>{{ universe_totals_text }}</p>
    </body>
    </html>
    """
    return render_template_string(html_template, star_systems=star_systems, universe_totals_text=universe_totals_text)

if __name__ == "__main__":
    app.run(debug=True)

