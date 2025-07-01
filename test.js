const express = require('express');
const fs = require('fs').promises;
const path = require('path');
const MidiWriter = require('midi-writer-js');

const app = express();
const port = 3000;

app.use(express.urlencoded({ extended: true }));
app.use(express.static('public'));

const notescount = 512;
const minorarray = [48, 50, 52, 53, 55, 57, 59, 60, 62, 64, 65, 67, 69, 71, 72, 74];
const shift = 16;

let saved = [];
let newMelodies = [];

// Generate single-note melodies
function generateInitialMelodies() {
	newMelodies = [];
	for (let i = 0; i < 16; i++) {
		newMelodies.push(Array(notescount).fill(i % minorarray.length));
	}
}

// Generate MIDI file for a melody
async function generateMidi(filename, notes) {
	const track = new MidiWriter.Track();
	track.addEvent(new MidiWriter.ProgramChangeEvent({ instrument: 24 }));
	let time = 0;
	for (let i = 0; i < notescount; i++) {
		const pitch = minorarray[notes[i]];
		track.addEvent(new MidiWriter.NoteEvent({
			pitch: [pitch],
			duration: 'T12',
			velocity: 100,
			startTick: time
		}));
		time += 12;
	}
	const write = new MidiWriter.Writer([track]);
	await fs.writeFile(filename, write.buildFile());
}

// Serve index
app.get('/', async (req, res) => {
	await fs.mkdir('public/midi', { recursive: true });
	for (let i = 0; i < newMelodies.length; i++) {
		await generateMidi(path.join('public', `midi/new${i}.mid`), newMelodies[i]);
	}
	for (let i = 0; i < saved.length; i++) {
		await generateMidi(path.join('public', `midi/saved${i}.mid`), saved[i]);
	}

	let html = `
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>MIDI Generator</title>
	   <script type="module">
		import { Midi } from 'https://cdn.jsdelivr.net/npm/@tonejs/midi@2.0.27/+esm';
		import * as Tone from 'https://cdn.jsdelivr.net/npm/tone@14.8.39/+esm';
		let currentSynth = null;
		window.playMidi = async function (url) {
			if (currentSynth) { currentSynth.dispose(); }
			currentSynth = new Tone.PolySynth().toDestination();
			const r = await fetch(url);
			const buf = await r.arrayBuffer();
			const midi = new Midi(buf);
			const now = Tone.now();
			for (const track of midi.tracks) {
				for (const note of track.notes) {
					currentSynth.triggerAttackRelease(note.name, note.duration, now + note.time, note.velocity);
				}
			}
		};
		window.stopMidi = function () {
			if (currentSynth) { currentSynth.dispose(); currentSynth = null; }
		};
		</script>
		<style>
			body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f4f4; }
			.container { display: flex; gap: 20px; }
			.column { flex: 1; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
			h2 { margin-top: 0; color: #333; }
			.melody { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; }
			.melody input, .melody button { margin-left: 10px; }
			canvas { width: 100%; height: 100px; margin-top: 10px; }
		</style>
	</head>
	<body>
		<button onclick="showRolls()">Show Piano Rolls</button>
		<button type="button" onclick="stopMidi()">⏹ Stop</button>
		<div class="container">
			<div class="column">
				<form method="post" action="/shuffle">
				<h2>Saved Melodies</h2>
				<button type="button" onclick="toggleSavedSelection()">Select All</button>
				<label for="shuffleCount">New melodies:</label>
				<select name="shuffleCount" id="shuffleCount">
					${Array.from({ length: 17 }, (_, i) => `<option value="${i}">${i}</option>`).join('')}
				</select>
				<button formaction="/shuffle" method="post">Shuffle Selected</button>
				<button formaction="/delete" method="post">Delete Selected ❌</button>
	`;

	saved.forEach((mel, i) => {
		const data = JSON.stringify(mel);
		html += `
			<div class="melody">
				<input type="checkbox" name="savedselect" value="${i}">
				<a href="/midi/saved${i}.mid" target="_blank">midi_${i}</a>
				<button type="button" onclick="playMidi('/midi/saved${i}.mid')">▶</button><br>
				<canvas id="saved-${i}" width="512" height="64" style="display:none;border:1px solid #aaa;"></canvas>
				<script>
				(() => {
					const canvas = document.getElementById('saved-${i}');
					const ctx = canvas.getContext('2d');
					const data = ${data};
					const scaleY = 4, scaleX = 2;
					for (let x = 0; x < data.length; x++) {
						const y = ${minorarray.length - 1} - data[x];
						ctx.fillRect(x * scaleX, y * scaleY, scaleX, scaleY);
					}
				})();
				</script>
			</div>
		`;
	});

	html += `
				</form>
			</div>
			<div class="column">
				<h2>New Melodies</h2>
				<form action="/save" method="post">
					<button type="submit" class="save-btn">Save Selected</button>
	`;

	newMelodies.forEach((mel, i) => {
		const data = JSON.stringify(mel);
		html += `
			<div class="melody">
				<input type="checkbox" name="select" value="${i}">
				<a href="/midi/new${i}.mid" target="_blank">midi_${i}</a>
				<button type="button" onclick="playMidi('/midi/new${i}.mid')">▶</button><br>
				<canvas id="new-${i}" width="512" height="64" style="display:none;border:1px solid #aaa;"></canvas>
				<script>
				(() => {
					const canvas = document.getElementById('new-${i}');
					const ctx = canvas.getContext('2d');
					const data = ${data};
					const scaleY = 4, scaleX = 2;
					for (let x = 0; x < data.length; x++) {
						const y = ${minorarray.length - 1} - data[x];
						ctx.fillRect(x * scaleX, y * scaleY, scaleX, scaleY);
					}
				})();
				</script>
			</div>
		`;
	});

	html += `
				</form>
			</div>
		</div>
		<script>
		function showRolls() {
			for (let i = 0; i < ${newMelodies.length}; i++) {
				const el = document.getElementById('new-' + i);
				el.style.display = (el.style.display === 'none') ? 'block' : 'none';
			}
			for (let i = 0; i < ${saved.length}; i++) {
				const el = document.getElementById('saved-' + i);
				el.style.display = (el.style.display === 'none') ? 'block' : 'none';
			}
		}
		let allSelected = false;

		function toggleSavedSelection() {
			const checkboxes = document.querySelectorAll('input[name="savedselect"]');
			checkboxes.forEach(cb => cb.checked = !allSelected);
			allSelected = !allSelected;

			const btn = document.querySelector('button[onclick="toggleSavedSelection()"]');
			if (btn) btn.textContent = allSelected ? 'Deselect All' : 'Select All';
		}

		</script>
	</body>
	</html>
	`;
	res.send(html);
});

// Save selected new melodies to saved list
app.post('/save', express.urlencoded({ extended: true }), (req, res) => {
	const selected = req.body.select;
	if (Array.isArray(selected)) {
		selected.forEach(i => saved.push(newMelodies[i]));
	} else if (selected !== undefined) {
		saved.push(newMelodies[selected]);
	}
	res.redirect('/');
});

// Delete from saved
app.post('/delete', express.urlencoded({ extended: true }), (req, res) => {
	const selected = req.body.savedselect;

	if (Array.isArray(selected)) {
		// Convert to numbers and sort in reverse order to prevent reindexing issues
		const indices = selected.map(Number).sort((a, b) => b - a);
		for (const i of indices) {
			if (!isNaN(i) && i >= 0 && i < saved.length) {
				saved.splice(i, 1);
			}
		}
	} else if (!isNaN(parseInt(selected))) {
		const i = parseInt(selected);
		if (i >= 0 && i < saved.length) {
			saved.splice(i, 1);
		}
	}

	res.redirect('/');
});

app.post('/shuffle', express.urlencoded({ extended: true }), (req, res) => {
	const selected = req.body.savedselect;
	const requestedCount = parseInt(req.body.shuffleCount) || 0;

	function perfectShuffle(m1, m2, shift = 16) {
		const interleaved = new Array(m1.length * 2);
		for (let i = 0; i < m1.length; i++) {
			interleaved[2 * i] = m1[i];
			interleaved[2 * i + 1] = m2[i];
		}

		const out1 = [], out2 = [];
		for (let i = 0; i < m1.length; i++) {
			out1[i] = interleaved[i];
			out2[i] = interleaved[(i + shift) % interleaved.length];
		}

		return [out1, out2];
	}

	let selectedMelodies = [];

	if (Array.isArray(selected)) {
		selectedMelodies = selected.map(i => saved[+i]);
	} else if (selected !== undefined) {
		selectedMelodies = [saved[+selected]];
	}

	const actualCount = selectedMelodies.length;

	// Always shuffle pairs at minimum
	let result = [];

	if (actualCount < 2) {
		return res.redirect('/'); // Nothing to do
	}

	const totalNeeded = Math.max(requestedCount, selectedMelodies.length);
	const extraCount = totalNeeded - actualCount;

	// Create extra single-note melodies
	const singleNotes = [];
	for (let i = 0; i < extraCount; i++) {
		const noteIndex = i % minorarray.length;
		const melody = Array(notescount).fill(noteIndex);
		singleNotes.push(melody);
	}

	const pool = [...selectedMelodies, ...singleNotes];

	// Shuffle pool to avoid repeating
	for (let i = pool.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1));
		[pool[i], pool[j]] = [pool[j], pool[i]];
	}

	// Mix in pairs
	for (let i = 0; i < Math.floor(pool.length / 2); i++) {
		const m1 = pool[2 * i];
		const m2 = pool[2 * i + 1];
		const [a, b] = perfectShuffle(m1, m2, shift);
		result.push(a, b);
		if (result.length >= totalNeeded) break;
	}

	newMelodies = result.slice(0, totalNeeded);
	res.redirect('/');
});

generateInitialMelodies();
app.listen(port, () => {
	console.log(`http://localhost:${port}`);
});