<h1>Ukázka</h1>

<section>
  <h2>Nadpisy</h2>
  <h1>Nadpis 1</h1>
  <h2>Nadpis 2</h2>
  <h3>Nadpis 3</h3>
  <h4>Nadpis 4</h4>
  <h5>Nadpis 5</h5>
  <h6>Nadpis 6</h6>
</section>

<section>
  <h2>Text a odkazy</h2>
  <p>Toto je odstavec s <a href="#">odkazem</a> uvnitř.</p>
  <blockquote>Příklad citace</blockquote>
  <pre><code>ukázka kódu</code></pre>
</section>

<section>
  <h2>Seznamy</h2>
  <ul>
    <li>Nepočítaný bod 1</li>
    <li>Nepočítaný bod 2</li>
  </ul>
  <ol>
    <li>Počítaný bod 1</li>
    <li>Počítaný bod 2</li>
  </ol>
</section>

<section>
  <h2>Formulář</h2>
  <form class="auth-form">
    <div class="auth-form__field">
      <label for="demo-input">Vstup</label>
      <input id="demo-input" type="text" class="auth-form__input" placeholder="Textový vstup" />
    </div>
    <button type="submit">Odeslat</button>
  </form>
</section>

<section>
  <h2>Custom Select</h2>
  <div data-island="select">
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
      <div>
        <label>Vyberte roli</label>
        <div class="select" data-select data-value="user">
          <button type="button" class="select-button" aria-haspopup="listbox" aria-expanded="false">user</button>
          <ul class="select-list" role="listbox" hidden>
            <li role="option" class="select-option" data-value="user" aria-selected="true">user</li>
            <li role="option" class="select-option" data-value="admin" aria-selected="false">admin</li>
            <li role="option" class="select-option" data-value="superadmin" aria-selected="false">superadmin</li>
          </ul>
        </div>
      </div>
      <div>
        <label>Jednoduchý výběr</label>
        <div class="select" data-select data-value="Option A">
          <button type="button" class="select-button" aria-haspopup="listbox" aria-expanded="false">Option A</button>
          <ul class="select-list" role="listbox" hidden>
            <li role="option" class="select-option" data-value="Option A" aria-selected="true">Option A</li>
            <li role="option" class="select-option" data-value="Option B" aria-selected="false">Option B</li>
            <li role="option" class="select-option" data-value="Option C" aria-selected="false">Option C</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <h2>Table</h2>
  <table>
    <thead>
      <tr><th>Header 1</th><th>Header 2</th></tr>
    </thead>
    <tbody>
      <tr><td>Cell 1</td><td>Cell 2</td></tr>
      <tr><td>Cell 3</td><td>Cell 4</td></tr>
    </tbody>
  </table>
</section>
