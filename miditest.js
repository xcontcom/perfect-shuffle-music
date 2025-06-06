const express = require('express');
const fs = require('fs').promises;
const path = require('path');
const MidiWriter = require('midi-writer-js'); // Use midi-writer-js

const app = express();
const port = 3000;

app.use(express.urlencoded({ extended: true }));
app.use(express.static('public')); // Serve static files (MIDI files, HTML)

// Configuration
const notescount = 256;
const minorarray = [48, 50, 52, 53, 55, 57, 59, 60, 62, 64, 65, 67, 69, 71, 72, 74];

const survivalsCount = 24;
const shift=8;

// MIDI Generation
async function generateMidi(filename, n) {
	// Ensure the directory exists
	const dir = path.dirname(filename);
	await fs.mkdir(dir, { recursive: true }).catch(err => {
		if (err.code !== 'EEXIST') {
			console.error(`Error creating directory ${dir}:`, err);
		}
	});

	const track = new MidiWriter.Track();
	
	// Set program to 24 (nylon-string guitar)
	track.addEvent(new MidiWriter.ProgramChangeEvent({ instrument: 24 }));
	
	// Add text meta event
	track.addEvent(new MidiWriter.TextEvent({ text: 'Genetic algorithm' }));

	// Add notes
	let time = 0;
	for (let i = 0; i < notescount; i++) {
		const nn = minorarray[n[i]];
		track.addEvent(new MidiWriter.NoteEvent({
			pitch: [nn],
			duration: 'T12', // 12 ticks duration
			velocity: 100,
			startTick: time
		}));
		time += 12;
	}

	// Create MIDI file
	const midi = new MidiWriter.Writer([track]);
	
	// Write MIDI file
	return fs.writeFile(filename, midi.buildFile())
		.catch(err => console.error(`Error writing MIDI file ${filename}:`, err));
}

// Population Management
async function filePop(notescount, population = null) {
	const filename = 'population.json';
	if (!population) {
		try {
			const data = await fs.readFile(filename, 'utf8');
			return JSON.parse(data);
		} catch (err) {
			// Initialize population if file doesn't exist
			population = Array(48).fill().map(() => Array(notescount).fill(0));
			for (let i = 0; i < 16; i++) {
				const startsound = i;
				for (let j = 0; j < notescount; j++) {
					population[i][j] = startsound;
					population[i + 16][j] = startsound;
					population[i + 32][j] = startsound;
				}
			}
			await fs.writeFile(filename, JSON.stringify(population));
			return population;
		}
	} else {
		await fs.writeFile(filename, JSON.stringify(population));
		return population;
	}
}


// Web Interface
app.get('/', async (req, res) => {
	const population = await filePop(notescount);
	
	// Generate MIDI files
	await Promise.all(
		Array.from({ length: 48 }, async (_, i) => {
			const filename = path.join('public', `midi/test${i}.mid`);
			await generateMidi(filename, population[i]);
		})
	);

	// Generate HTML form
	let html = `
		<h1>Music Generator</h1>
		<form method="post" action="/submit">
	`;
	for (let i = 0; i < 48; i++) {
		html += `
			<div>
				<a href="/midi/test${i}.mid" target="_blank">Sequence ${i}</a>
				<input type="checkbox" name="option${i}" value="1"> Select
			</div>
		`;
	}
	html += `
		<input type="submit" value="Evolve">
		<button formaction="/recreate" formmethod="post">Recreate</button>
		</form>
		<div id="console-log0" style="margin-top:10px;font-weight:bold;">Selected: 0</div>

		<script>
		document.addEventListener('DOMContentLoaded', () => {
			let counter = 0;
			const display = document.getElementById('console-log0');
			const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="option"]');
			function updateCounter() {
				counter = Array.from(checkboxes).filter(cb => cb.checked).length;
				display.textContent = 'Selected: ' + counter;
				display.style.color = counter === ${survivalsCount} ? 'lime' : counter > ${survivalsCount} ? 'red' : 'black';
			}
			checkboxes.forEach(cb => cb.addEventListener('change', updateCounter));
		});
		</script>
	`;
	res.send(html);
});

// Handle Form Submission
app.post('/submit', async (req, res) => {
	let population = await filePop(notescount);
	
	// Assign fitness scores
	for (let i = 0; i < 48; i++) {
		population[i].fitness = parseInt(req.body[`option${i}`] || 0);
	}
	console.log(req.body);
	
	// Sort by fitness
	population.sort((a, b) => b.fitness - a.fitness);

	// Create new population
	const newDudesCount = 32 - survivalsCount;
	const newPopulation = [];
	// Copy top survivalsCount individuals 
	for (let i = 0; i < survivalsCount; i++) {
		newPopulation[i] = population[i].slice(0, notescount);
	}
	// Add newDudesCount single-note arrays with random notes from minorarray 
	for (let i = 0; i < newDudesCount; i++) {
		const note = Math.floor(Math.random() * minorarray.length);
		newPopulation[i + survivalsCount] = Array(notescount).fill(note);
	}

	// Shuffle newPopulation
	for (let i = newPopulation.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1));
		[newPopulation[i], newPopulation[j]] = [newPopulation[j], newPopulation[i]];
	}

	// Perfect shuffle crossover
	const newPop = [];
	let k = 0;
	for (let i = 0; i < 16; i++) {
		const i2 = i + 16;
		const k2 = k + 1;
		const tarray = [];
		for (let j = 0; j < notescount; j++) {
			tarray[j * 2] = newPopulation[i][j];
			tarray[j * 2 + 1] = newPopulation[i2][j];
		}
		for (let j = 0; j < notescount; j++) {
			newPop[k] = newPop[k] || [];
			newPop[k2] = newPop[k2] || [];
			newPop[k][j] = tarray[j];
			newPop[k2][j] = tarray[(j + shift) % tarray.length];
		}
		k += 2;
		if (!('fitness' in newPopulation[i])) {
			newPop[k] = newPopulation[i].slice();
			k++;
		}
		if (!('fitness' in newPopulation[i2])) {
			newPop[k] = newPopulation[i2].slice();
			k++;
		}
	}

	// Update population
	population = newPop;
	await filePop(notescount, population);
	
	res.redirect('/');
});

app.post('/recreate', async (req, res) => {
	const population = Array(48).fill().map(() => Array(notescount).fill(0));
	for (let i = 0; i < 16; i++) {
		const startsound = i;
		for (let j = 0; j < notescount; j++) {
			population[i][j] = startsound;
			population[i + 16][j] = startsound;
			population[i + 32][j] = startsound;
		}
	}
	await fs.writeFile('population.json', JSON.stringify(population));
	res.redirect('/');
});

app.listen(port, () => {
	console.log(`Server running at http://localhost:${port}`);
});