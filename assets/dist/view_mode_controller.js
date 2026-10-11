import { Controller } from '@hotwired/stimulus';

/**
 * Picks the list view while the calendar is too narrow for the month grid, and the month view otherwise.
 * Only rendered until the visitor picks a view mode themselves, like the theme follows the system setting.
 */
export default class extends Controller {
  static targets = ['narrow'];

  connect() {
    this.observer = new ResizeObserver(this._fit.bind(this));
    this.observer.observe(this.element);
  }

  disconnect() {
    this.observer.disconnect();
  }

  _fit() {
    const minWidth = parseFloat(getComputedStyle(this.element).getPropertyValue('--calendar-grid-min-width'));
    const narrow = this.element.clientWidth < minWidth;
    if (this.narrowTarget.checked === narrow) {
      return;
    }

    // the checkbox is bound to the component's "narrow" prop, so changing it re-renders the calendar
    this.narrowTarget.checked = narrow;
    this.narrowTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }
}
