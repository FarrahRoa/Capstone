import { Component } from 'react';

/**
 * Prevents a schedule rendering bug from blanking the entire login page.
 */
export default class SchedulePanelErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
    }

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        console.error('Schedule panel failed to render', error, info);
    }

    render() {
        if (this.state.error) {
            return (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-600">
                    Schedule currently unavailable. Please contact administration or refresh the page.
                </div>
            );
        }
        return this.props.children;
    }
}
